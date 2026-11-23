<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/autoload.php';
require_once __DIR__ . '/Support/PublicKeyPublishFixture.php';

final class PublicKeyPublisherTest
{
    public function testDefaultPublicationAndRerunUseOnlyPublicKeyAndOneRegistration(): void
    {
        $f = new PublicKeyPublishFixture();
        try {
            [$code, $out, $err] = $f->command();
            assertSame(0, $code, $err); assertStringContains('Mode: publish', $out);
            assertStringContains('State: published', $out);
            $posted = unserialize(file_get_contents($f->directory . '/posted'), ['allowed_classes' => false]);
            assertSame(['public_key'], array_keys($posted)); assertSame($f->publicKey, $posted['public_key']);
            [$code, $out] = $f->command(); assertSame(0, $code); assertStringContains('State: existing', $out);
            assertSame(1, substr_count($f->requests(), 'POST '));
            assertSame([], glob($f->directory . '/*review*'));
        } finally { $f->close(); }
    }

    public function testOptionalSelectionAndDryRunDoNotPublishOtherKeys(): void
    {
        $a = new PublicKeyPublishFixture(); $b = new PublicKeyPublishFixture();
        try {
            $this->addSource($a, $b);
            [$code, $out] = $a->command(['--dry-run', '--key=' . $b->fingerprint]);
            assertSame(0, $code); assertStringContains('State: missing', $out);
            assertStringNotContains($a->fingerprint, $out); assertSame(false, str_contains($a->requests(), 'POST '));
            [$code, $out] = $a->command(['--key=' . strtolower($b->fingerprint), '--key=' . $b->fingerprint]);
            assertSame(0, $code); assertStringContains('State: published', $out); assertStringNotContains($a->fingerprint, $out);
            assertSame(1, substr_count($a->requests(), 'POST '));
            [$code, $out] = $a->command();
            assertSame(0, $code); assertStringContains('State: existing', $out); assertStringContains('State: published', $out);
            assertSame(2, substr_count($a->requests(), 'POST '));
        } finally { $a->close(); $b->close(); }
    }

    public function testInvalidSelectionAndOldReviewFlagsStopBeforeRequests(): void
    {
        $f = new PublicKeyPublishFixture();
        try {
            foreach ([['--key=short'], ['--key=' . $f->fingerprint, '--key=' . str_repeat('A', 40)], ['--dry-run', '--key=']] as $args) {
                [$code] = $f->command($args); assertSame(1, $code);
            }
            foreach (['--plan=' . $f->keyPath, '--publish=' . $f->directory . '/old.txt', '--plan', '--publish'] as $option) {
                [$code, , $err] = $f->command([$option]); assertSame(1, $code);
                assertStringContains('Use --dry-run to preview', $err);
            }
            assertSame('', $f->requests()); assertSame($f->publicKey, file_get_contents($f->keyPath));
        } finally { $f->close(); }
    }

    public function testUnsafeUnselectedSourceStopsBeforeAnyNetwork(): void
    {
        $a = new PublicKeyPublishFixture(); $b = new PublicKeyPublishFixture();
        try {
            $b->source(str_replace('PRIVATE KEY BLOCK', 'PUBLIC KEY BLOCK', $b->secretKey));
            $this->addSource($a, $b);
            foreach ([[], ['--key=' . $a->fingerprint]] as $args) {
                [$code, , $err] = $a->command($args);
                assertSame(1, $code); assertStringContains('secret-key packets', $err);
                assertSame('', $a->requests());
            }
        } finally { $a->close(); $b->close(); }
    }

    public function testUncertainResponsesAndConcurrentRegistrationReconcileWithoutDuplicates(): void
    {
        foreach (['drop', 'race', 'readback-failure', 'deny'] as $mode) {
            $f = new PublicKeyPublishFixture();
            try {
                file_put_contents($f->directory . '/register-mode', $mode);
                [$code, $out] = $f->command();
                assertSame(in_array($mode, ['drop', 'race'], true) ? 0 : 1, $code, $out);
                assertStringContains('State: ' . match ($mode) { 'readback-failure' => 'unknown', 'deny' => 'refused', default => 'published' }, $out);
                if ($mode === 'readback-failure') {
                    unlink($f->directory . '/fail-reads');
                    [$code, $out] = $f->command(); assertSame(0, $code); assertStringContains('State: existing', $out);
                }
                assertSame(1, substr_count($f->requests(), 'POST '));
            } finally { $f->close(); }
        }
    }

    public function testDefaultBatchKeepsSuccessWhenAnotherKeyIsRefusedAndRetries(): void
    {
        $a = new PublicKeyPublishFixture(); $b = new PublicKeyPublishFixture();
        try {
            $this->addSource($a, $b);
            file_put_contents($a->directory . '/deny-fp', $b->fingerprint);
            [$code, $out] = $a->command();
            assertSame(1, $code); assertStringContains('State: published', $out); assertStringContains('State: refused', $out);
            unlink($a->directory . '/deny-fp');
            [$code, $out] = $a->command();
            assertSame(0, $code); assertStringContains('State: existing', $out); assertStringContains('State: published', $out);
            assertSame(3, substr_count($a->requests(), 'POST '));
        } finally { $a->close(); $b->close(); }
    }

    public function testLocallyRefusedKeyDoesNotPreventEligibleKeyPublication(): void
    {
        $a = new PublicKeyPublishFixture(); $b = new PublicKeyPublishFixture();
        try {
            $this->addSource($a, $b); $a->source(null, 'renamed');
            [$code, $out] = $a->command();
            assertSame(1, $code); assertStringContains('Source name differs', $out);
            assertStringContains('State: published', $out); assertStringContains('State: refused', $out);
            assertSame(1, substr_count($a->requests(), 'POST '));
            assertStringNotContains(strtolower($a->fingerprint), $a->requests());
        } finally { $a->close(); $b->close(); }
    }

    public function testSourceChangesDuringPreflightStopWritesAndCanBeRetried(): void
    {
        $a = new PublicKeyPublishFixture(); $b = new PublicKeyPublishFixture();
        try {
            $this->addSource($a, $b);
            file_put_contents($a->directory . '/source-change', serialize([$a->keyPath, $a->publicKey . "\n"]));
            [$code, $out] = $a->command(['--key=' . $a->fingerprint, '--key=' . $b->fingerprint]);
            assertSame(1, $code); assertStringContains('Source changed during publication', $out);
            assertStringContains('State: unknown', $out); assertSame(false, str_contains($a->requests(), 'POST '));
            [$code, $out] = $a->command();
            assertSame(0, $code); assertSame(2, substr_count($out, 'State: published'));
            assertSame(2, substr_count($a->requests(), 'POST '));
        } finally { $a->close(); $b->close(); }
    }

    private function addSource(PublicKeyPublishFixture $destination, PublicKeyPublishFixture $source): void
    {
        copy($source->keyPath, $destination->root . '/records/public-keys/' . basename($source->keyPath));
        copy($source->identityPath, $destination->root . '/records/identity/' . basename($source->identityPath));
    }
}
