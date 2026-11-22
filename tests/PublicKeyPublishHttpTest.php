<?php
declare(strict_types=1);

require_once __DIR__ . '/SingleRequestHttpTest.php';
require_once __DIR__ . '/Support/PublicKeyPublishFixture.php';

use ForumRewrite\Support\PublicKeyPublishDestination;
use ForumRewrite\Write\SignedPostTransaction;

final class PublicKeyPublishHttpTest
{
    public function testOrdinaryCliPublishesToActualApiAndRerunsWithoutWrites(): void
    {
        $f = new PublicKeyPublishFixture();
        [$server, $repo] = SingleRequestHttpTest::server();
        try {
            $git = new SignedPostTransaction($repo);
            $before = $git->git(['rev-parse', 'HEAD']);
            $source = file_get_contents($f->identityPath);
            $plan = $f->directory . '/review.txt';
            [$code, $out, $err] = $f->command(['--plan=' . $plan], $server->url);
            assertSame(0, $code, $err); assertStringContains('State: missing', $out);
            assertSame($before, $git->git(['rev-parse', 'HEAD']));
            $args = ['--publish=' . $plan, '--key=' . $f->fingerprint];
            [$code, $out, $err] = $f->command($args, $server->url);
            assertSame(0, $code, $err . $out); assertStringContains('State: published', $out);
            $after = $git->git(['rev-parse', 'HEAD']); assertSame(false, $before === $after);
            $profile = (new PublicKeyPublishDestination($server->url))->profile($f->fingerprint);
            assertSame('existing', $profile['state']); assertSame('no', $profile['approved']);
            assertSame('publisher-fixture', $profile['name']);
            assertSame(trim($f->publicKey), trim(file_get_contents($repo . '/records/public-keys/' . basename($f->keyPath))));
            assertSame(true, is_file($repo . '/records/identity/' . basename($f->identityPath)));
            [$code, $out, $err] = $f->command($args, $server->url);
            assertSame(0, $code, $err); assertStringContains('State: existing', $out);
            assertSame($after, $git->git(['rev-parse', 'HEAD']));
            assertSame($source, file_get_contents($f->identityPath));
        } finally { $server->close(); $f->close(); }
    }

    public function testActualMembersOnlyDestinationRefusesWithoutWrites(): void
    {
        $f = new PublicKeyPublishFixture();
        [$server, $repo] = SingleRequestHttpTest::server(['FORUM_APPROVED_MEMBERS_ONLY' => 'true']);
        try {
            $git = new SignedPostTransaction($repo); $before = $git->git(['rev-parse', 'HEAD']);
            [$code, $out] = $f->command([], $server->url);
            assertSame(1, $code); assertStringContains('State: refused', $out);
            assertSame($before, $git->git(['rev-parse', 'HEAD']));
        } finally { $server->close(); $f->close(); }
    }

    public function testOversizeAndTlsFailuresStayUnknownAndDoNotPublish(): void
    {
        $f = new PublicKeyPublishFixture();
        try {
            $f->response(200, str_repeat('x', 65537));
            [$code, $out] = $f->command(); assertSame(1, $code);
            assertStringContains('State: unknown', $out); assertStringContains('size limit', $out);
            [$code, $out] = $f->command(['--timeout=1'], str_replace('http:', 'https:', $f->url));
            assertSame(1, $code); assertStringContains('State: unknown', $out);
            assertSame(false, str_contains($f->requests(), 'POST '));
            $f->response(404, "profile not found\nextra\n");
            [$code, $out] = $f->command(); assertSame(1, $code); assertStringContains('State: unknown', $out);
        } finally { $f->close(); }
    }

    public function testTimeoutIsUnknownAndRequiresRetry(): void
    {
        $f = new PublicKeyPublishFixture();
        try {
            file_put_contents($f->directory . '/delay', 'yes');
            [$code, $out] = $f->command(['--timeout=1']);
            assertSame(1, $code); assertStringContains('State: unknown', $out);
            assertSame(false, str_contains($f->requests(), 'POST '));
            unlink($f->directory . '/delay');
            [$code, $out] = $f->command();
            assertSame(0, $code); assertStringContains('State: missing', $out);
        } finally { $f->close(); }
    }

    public function testVerifiedNameChangeAndBrokenRetirementEvidenceRefuseBeforeNetwork(): void
    {
        $f = new PublicKeyPublishFixture();
        try {
            mkdir($f->root . '/records/profile-names');
            $id = 'profile-name-' . str_repeat('a', 32);
            $path = $f->root . '/records/profile-names/' . $id . '.txt';
            file_put_contents($path, "Operation: set-name\nRecord-ID: $id\nAuthor-Identity-ID: openpgp:" . strtolower($f->fingerprint)
                . "\nUsername: renamed\nCreated-At: " . gmdate('Y-m-d\TH:i:s\Z') . "\n\n");
            $f->gpg(['--armor', '--detach-sign', $path]);
            [$code, $out] = $f->command();
            assertSame(1, $code); assertStringContains('Name: renamed', $out);
            assertStringContains('State: refused', $out); assertSame('', $f->requests());
            unlink($path); unlink($path . '.asc');
            mkdir($f->root . '/records/key-retirements');
            file_put_contents($f->root . '/records/key-retirements/openpgp-' . strtolower($f->fingerprint) . '.txt', "Version: 1\n\n");
            [$code, $out] = $f->command();
            assertSame(1, $code); assertStringContains('retirement evidence', $out);
            assertSame('', $f->requests());
        } finally { $f->close(); }
    }
}
