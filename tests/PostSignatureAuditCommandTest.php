<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

final class PostSignatureAuditCommandTest
{
    public function testCommandReportsSignedInvalidUnknownMissingAndAnonymousPosts(): void
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-signature-audit-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot . '/records/posts', 0777, true);
        mkdir($repositoryRoot . '/records/identity', 0777, true);
        mkdir($repositoryRoot . '/records/public-keys', 0777, true);
        $this->installKnownIdentity($repositoryRoot);

        $signedRecord = $this->fixture('signed-post.txt');
        $signature = $this->fixture('signed-post.txt.asc');
        file_put_contents($repositoryRoot . '/records/posts/signed-test.txt', $signedRecord);
        file_put_contents($repositoryRoot . '/records/posts/signed-test.txt.asc', $signature);
        file_put_contents($repositoryRoot . '/records/posts/invalid-signed.txt', str_replace('signed-test', 'invalid-signed', $signedRecord));
        file_put_contents($repositoryRoot . '/records/posts/invalid-signed.txt.asc', $signature);
        file_put_contents(
            $repositoryRoot . '/records/posts/unknown-key.txt',
            "Post-ID: unknown-key\n"
            . "Created-At: 2026-07-19T20:01:00Z\n"
            . "Board-Tags: general\n"
            . "Author-Identity-ID: openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\n"
            . "Subject: Unknown key\n"
            . "\nUnknown key body.\n"
        );
        file_put_contents($repositoryRoot . '/records/posts/unknown-key.txt.asc', $signature);
        file_put_contents(
            $repositoryRoot . '/records/posts/authored-missing.txt',
            "Post-ID: authored-missing\n"
            . "Created-At: 2026-07-19T20:02:00Z\n"
            . "Board-Tags: general\n"
            . "Author-Identity-ID: openpgp:15c2ef95baee78f4c635a43323b6ae7ec7af9919\n"
            . "Subject: Missing signature\n"
            . "\nMissing signature body.\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/posts/anonymous.txt',
            "Post-ID: anonymous\n"
            . "Created-At: 2026-07-19T20:03:00Z\n"
            . "Board-Tags: general\n"
            . "Subject: Anonymous\n"
            . "\nAnonymous body.\n"
        );

        [$exitCode, $output] = $this->runCommandAllowFailure(sprintf(
            'php %s %s',
            escapeshellarg(__DIR__ . '/../scripts/audit_post_signatures.php'),
            escapeshellarg($repositoryRoot)
        ));

        assertSame(2, $exitCode);
        assertStringContains('Post Signature Audit', $output);
        assertStringContains('total_posts: 5', $output);
        assertStringContains('signed_valid: 1', $output);
        assertStringContains('missing_signature: 1', $output);
        assertStringContains('invalid_signature: 1', $output);
        assertStringContains('unknown_author_key: 1', $output);
        assertStringContains('anonymous_unsigned: 1', $output);
        assertStringContains("signed_valid\tsigned-test\trecords/posts/signed-test.txt", $output);
        assertStringContains("missing_signature\tauthored-missing\trecords/posts/authored-missing.txt", $output);
        assertStringContains("invalid_signature\tinvalid-signed\trecords/posts/invalid-signed.txt", $output);
        assertStringContains("unknown_author_key\tunknown-key\trecords/posts/unknown-key.txt", $output);
        assertStringContains("anonymous_unsigned\tanonymous\trecords/posts/anonymous.txt", $output);
    }

    public function testCommandCountsUnsignedNamedAnonymousAndInvalidReactions(): void
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-reaction-audit-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot . '/records/thread-labels', 0777, true);
        mkdir($repositoryRoot . '/records/post-reactions', 0777, true);
        $named = "Author-Identity-ID: openpgp:15c2ef95baee78f4c635a43323b6ae7ec7af9919\n";
        file_put_contents($repositoryRoot . '/records/thread-labels/scored.txt', "Record-ID: thread-label-scored\nCreated-At: 2026-10-23T00:00:00Z\nThread-ID: t1\nOperation: add\nLabels: like\n{$named}\n");
        file_put_contents($repositoryRoot . '/records/thread-labels/unscored.txt', "Record-ID: thread-label-unscored\nCreated-At: 2026-10-23T00:00:00Z\nThread-ID: t1\nOperation: add\nLabels: music\n{$named}\n");
        file_put_contents($repositoryRoot . '/records/thread-labels/anonymous.txt', "Record-ID: thread-label-anonymous\nCreated-At: 2026-10-23T00:00:00Z\nThread-ID: t1\nOperation: add\nLabels: like\n\n");
        file_put_contents($repositoryRoot . '/records/post-reactions/invalid.txt', "Record-ID: post-reaction-invalid\nCreated-At: 2026-10-23T00:00:00Z\nPost-ID: p1\nOperation: add\nTags: like\n{$named}\n");
        file_put_contents($repositoryRoot . '/records/post-reactions/invalid.txt.asc', "not a signature\n");
        file_put_contents($repositoryRoot . '/records/thread-labels/unified.txt', "Record-ID: thread-label-unified\nCreated-At: 2026-10-23T00:00:00Z\nTarget-Kind: thread\nTarget-ID: t1\nOperation: add\nTags: like\n{$named}\n");

        [$exitCode, $output] = $this->runCommandAllowFailure(sprintf(
            'php %s %s',
            escapeshellarg(__DIR__ . '/../scripts/audit_post_signatures.php'),
            escapeshellarg($repositoryRoot)
        ));

        assertSame(0, $exitCode);
        assertStringContains('total_reactions: 5', $output);
        assertStringContains('reaction_verified: 0', $output);
        assertStringContains('reaction_unsigned_named: 3', $output);
        assertStringContains('reaction_invalid: 1', $output);
        assertStringContains('reaction_anonymous: 1', $output);
        assertStringContains('scored_label_unsigned_named: 2', $output);
        assertStringContains("reaction_unsigned_named\trecords/thread-labels/scored.txt\topenpgp:15c2ef95baee78f4c635a43323b6ae7ec7af9919\tlike", $output);
        assertStringContains("reaction_invalid\trecords/post-reactions/invalid.txt", $output);
        assertStringContains("reaction_unsigned_named\trecords/thread-labels/unified.txt\topenpgp:15c2ef95baee78f4c635a43323b6ae7ec7af9919\tlike", $output);
    }

    private function installKnownIdentity(string $repositoryRoot): void
    {
        $fingerprintUpper = '15C2EF95BAEE78F4C635A43323B6AE7EC7AF9919';
        $fingerprintLower = strtolower($fingerprintUpper);
        $publicKey = $this->fixture('public-key.asc');
        file_put_contents($repositoryRoot . '/records/public-keys/openpgp-' . $fingerprintUpper . '.asc', $publicKey);
        file_put_contents(
            $repositoryRoot . '/records/identity/identity-openpgp-' . $fingerprintLower . '.txt',
            "Post-ID: identity-openpgp-{$fingerprintLower}\n"
            . "Board-Tags: identity\n"
            . "Subject: identity bootstrap\n"
            . "Username: signature-test\n"
            . "Identity-ID: openpgp:{$fingerprintLower}\n"
            . "Signer-Fingerprint: {$fingerprintUpper}\n"
            . "Bootstrap-By-Post: anonymous\n"
            . "Bootstrap-By-Thread: anonymous\n"
            . "\n{$publicKey}"
        );
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/openpgp_signature/' . $name);
    }

    /**
     * @return array{int,string}
     */
    private function runCommandAllowFailure(string $command): array
    {
        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
                . '.'
            );
        }
    }
}

if (!function_exists('assertStringContains')) {
    function assertStringContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that output contains: ' . $needle);
        }
    }
}
