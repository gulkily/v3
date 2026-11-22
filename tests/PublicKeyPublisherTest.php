<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/autoload.php';
require_once __DIR__ . '/Support/PublicKeyPublishFixture.php';

final class PublicKeyPublisherTest
{
    public function testReviewPublishAndRerunUseOnlyPublicKeyAndOneRegistration(): void
    {
        $f = new PublicKeyPublishFixture();
        try {
            $plan = $f->directory . '/plan.txt';
            [$code, , $err] = $f->command(['--plan=' . $plan]); assertSame(0, $code, $err);
            $text = file_get_contents($plan); assertStringContains('Version: 1', $text);
            assertSame(false, str_contains($text, 'BEGIN PGP'));
            $args = ['--publish=' . $plan, '--key=' . $f->fingerprint];
            [$code, $out, $err] = $f->command($args); assertSame(0, $code, $err); assertStringContains('State: published', $out);
            $posted = unserialize(file_get_contents($f->directory . '/posted'), ['allowed_classes' => false]);
            assertSame(['public_key'], array_keys($posted)); assertSame($f->publicKey, $posted['public_key']);
            [$code, $out] = $f->command($args); assertSame(0, $code); assertStringContains('State: existing', $out);
            assertSame(1, substr_count($f->requests(), 'POST '));
            assertSame($text, file_get_contents($plan));
        } finally { $f->close(); }
    }

    public function testPlanTargetSourceAndSelectionChangesStopBeforeWrites(): void
    {
        $f = new PublicKeyPublishFixture();
        try {
            $plan = $f->directory . '/plan.txt';
            [$code] = $f->command(['--plan=' . $plan]); assertSame(0, $code);
            $original = file_get_contents($plan);
            foreach ([str_replace($f->url, $f->url . '/other', $original), str_replace('Source-SHA256: ', 'Source-SHA256: bad', $original), str_replace('Name: publisher-fixture', 'Name: changed', $original)] as $changed) {
                file_put_contents($plan, $changed);
                [$code] = $f->command(['--publish=' . $plan, '--key=' . $f->fingerprint]); assertSame(1, $code);
            }
            file_put_contents($plan, $original);
            foreach ([[], ['--key=' . str_repeat('A', 40)]] as $args) {
                [$code] = $f->command(array_merge(['--publish=' . $plan], $args)); assertSame(1, $code);
            }
            $f->source(null, 'changed');
            [$code] = $f->command(['--publish=' . $plan, '--key=' . $f->fingerprint]); assertSame(1, $code);
            assertSame(false, str_contains($f->requests(), 'POST '));
            $before = file_get_contents($f->keyPath);
            [$code] = $f->command(['--plan=' . $f->keyPath]); assertSame(1, $code);
            assertSame($before, file_get_contents($f->keyPath));
        } finally { $f->close(); }
    }

    public function testUncertainResponsesAndConcurrentRegistrationReconcileWithoutDuplicates(): void
    {
        foreach (['drop', 'race', 'readback-failure', 'deny'] as $mode) {
            $f = new PublicKeyPublishFixture();
            try {
                $plan = $f->directory . '/plan.txt';
                [$code] = $f->command(['--plan=' . $plan]); assertSame(0, $code);
                file_put_contents($f->directory . '/register-mode', $mode);
                $args = ['--publish=' . $plan, '--key=' . $f->fingerprint];
                [$code, $out] = $f->command($args);
                assertSame(in_array($mode, ['drop', 'race'], true) ? 0 : 1, $code, $out);
                assertStringContains('State: ' . match ($mode) { 'readback-failure' => 'unknown', 'deny' => 'refused', default => 'published' }, $out);
                if ($mode === 'readback-failure') {
                    unlink($f->directory . '/fail-reads');
                    [$code, $out] = $f->command($args); assertSame(0, $code); assertStringContains('State: existing', $out);
                }
                assertSame(1, substr_count($f->requests(), 'POST '));
            } finally { $f->close(); }
        }
    }

    public function testMixedSelectionKeepsSuccessWhenAnotherKeyIsRefused(): void
    {
        $a = new PublicKeyPublishFixture(); $b = new PublicKeyPublishFixture();
        try {
            copy($b->keyPath, $a->root . '/records/public-keys/' . basename($b->keyPath));
            copy($b->identityPath, $a->root . '/records/identity/' . basename($b->identityPath));
            $plan = $a->directory . '/plan.txt';
            [$code] = $a->command(['--plan=' . $plan]); assertSame(0, $code);
            file_put_contents($a->directory . '/deny-fp', $b->fingerprint);
            [$code, $out] = $a->command(['--publish=' . $plan, '--key=' . $a->fingerprint, '--key=' . $b->fingerprint]);
            assertSame(1, $code); assertStringContains('State: published', $out); assertStringContains('State: refused', $out);
            unlink($a->directory . '/deny-fp');
            [$code, $out] = $a->command(['--publish=' . $plan, '--key=' . $a->fingerprint, '--key=' . $b->fingerprint]);
            assertSame(0, $code); assertStringContains('State: existing', $out); assertStringContains('State: published', $out);
            assertSame(3, substr_count($a->requests(), 'POST '));
        } finally { $a->close(); $b->close(); }
    }

    public function testEverySelectedSourceIsRevalidatedBeforeTheFirstPost(): void
    {
        $a = new PublicKeyPublishFixture(); $b = new PublicKeyPublishFixture();
        try {
            $other = $a->root . '/records/identity/' . basename($b->identityPath);
            copy($b->keyPath, $a->root . '/records/public-keys/' . basename($b->keyPath));
            copy($b->identityPath, $other);
            $plan = $a->directory . '/plan.txt';
            [$code] = $a->command(['--plan=' . $plan]); assertSame(0, $code);
            $before = file_get_contents($plan);
            [$code] = $a->command(['--plan=' . $plan]); assertSame(1, $code);
            assertSame($before, file_get_contents($plan), 'Preview must not overwrite an existing file.');
            file_put_contents($other, str_replace('Username: publisher-fixture', 'Username: renamed', file_get_contents($other)));
            [$code] = $a->command(['--publish=' . $plan, '--key=' . $a->fingerprint, '--key=' . $b->fingerprint]);
            assertSame(1, $code); assertSame(false, str_contains($a->requests(), 'POST '));
            copy($b->identityPath, $other);
            // Same identity, different exact source bytes: still requires a fresh preview.
            file_put_contents($a->keyPath, $a->publicKey . "\n");
            [$code] = $a->command(['--publish=' . $plan, '--key=' . $a->fingerprint]);
            assertSame(1, $code); assertSame(false, str_contains($a->requests(), 'POST '));
        } finally { $a->close(); $b->close(); }
    }
}
