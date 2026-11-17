<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Security\OpenPgpSignatureVerifier;

/** One GPG home per signer key within a process: fewer launches, same answers. */
final class OpenPgpKeyReuseTest
{
    private const POST_SIGNER = '15C2EF95BAEE78F4C635A43323B6AE7EC7AF9919';
    private const LABEL_SIGNER = '066236E516A3B74EC51B3B423A26034600DE67FD';

    public function testSecondCheckWithTheSameKeyRunsOnlyVerify(): void
    {
        [$verifier, $log] = $this->verifier();
        try {
            assertSame('ok', $this->post($verifier)['status']);
            $first = $this->runs($log);
            assertSame(true, $first >= 3);
            assertSame('ok', $this->post($verifier)['status']);
            assertSame($first + 1, $this->runs($log));
        } finally {
            $verifier->releasePreparedKeys();
        }
    }

    public function testPreparedKeyRejectsSignaturesFromOtherKeysAndTamperedText(): void
    {
        [$verifier] = $this->verifier();
        try {
            assertSame('ok', $this->post($verifier)['status']);
            // The label is signed by another key; the prepared post signer must not accept it.
            $other = $verifier->verifyDetached($this->fixture('public-key.asc'), $this->fixture('signed-label.txt'), $this->fixture('signed-label.txt.asc'), self::POST_SIGNER);
            assertSame(false, $other['ok']);
            $tampered = $verifier->verifyDetached($this->fixture('public-key.asc'), str_replace('signed-test', 'altered-test', $this->fixture('signed-post.txt')), $this->fixture('signed-post.txt.asc'), self::POST_SIGNER);
            assertSame('signature_verification_failed', $tampered['status']);
            // A wrong expected fingerprint is still caught after reuse.
            $mismatch = $verifier->verifyDetached($this->fixture('public-key.asc'), $this->fixture('signed-post.txt'), $this->fixture('signed-post.txt.asc'), self::LABEL_SIGNER);
            assertSame(false, $mismatch['ok']);
            assertSame('ok', $this->post($verifier)['status']);
        } finally {
            $verifier->releasePreparedKeys();
        }
    }

    public function testDifferentKeyBytesForTheSameFingerprintGetAFreshHome(): void
    {
        [$verifier] = $this->verifier();
        try {
            assertSame('ok', $this->post($verifier)['status']);
            // Another key's bytes claimed under the prepared fingerprint must not reuse its home.
            $swapped = $verifier->verifyDetached($this->fixture('reaction-public-key.asc'), $this->fixture('signed-post.txt'), $this->fixture('signed-post.txt.asc'), self::POST_SIGNER);
            assertSame(false, $swapped['ok']);
            assertSame('ok', $verifier->verifyDetached($this->fixture('reaction-public-key.asc'), $this->fixture('signed-label.txt'), $this->fixture('signed-label.txt.asc'), self::LABEL_SIGNER)['status']);
        } finally {
            $verifier->releasePreparedKeys();
        }
    }

    public function testHomesAreRemovedOnReleaseAndAfterFailedImports(): void
    {
        $before = $this->homes();
        [$verifier] = $this->verifier();
        assertSame('ok', $this->post($verifier)['status']);
        assertSame(count($before) + 1, count($this->homes()));
        $broken = "-----BEGIN PGP PUBLIC KEY BLOCK-----\n\nbm90IGEga2V5\n-----END PGP PUBLIC KEY BLOCK-----\n";
        assertSame(false, $verifier->verifyDetached($broken, $this->fixture('signed-post.txt'), $this->fixture('signed-post.txt.asc'), self::POST_SIGNER)['ok']);
        assertSame(count($before) + 1, count($this->homes()));
        $verifier->releasePreparedKeys();
        assertSame($before, $this->homes());
    }

    public function testHomesLeftByAKilledProcessAreRemovedByTheNextVerifier(): void
    {
        // A hard import deadline kills the process with SIGKILL, so no cleanup runs.
        $dead = $this->deadPid();
        $stale = sys_get_temp_dir() . '/forum-rewrite-gpg-keys-' . $dead . '-' . bin2hex(random_bytes(6));
        mkdir($stale . '/home', 0700, true);
        file_put_contents($stale . '/home/key.asc', 'left behind');
        $live = sys_get_temp_dir() . '/forum-rewrite-gpg-keys-' . getmypid() . '-' . bin2hex(random_bytes(6));
        mkdir($live, 0700);
        [$verifier] = $this->verifier();
        try {
            assertSame('ok', $this->post($verifier)['status']);
            assertSame(false, is_dir($stale));
            assertSame(true, is_dir($live));
        } finally {
            $verifier->releasePreparedKeys();
            @rmdir($live);
        }
    }

    public function testRemovedHomeIsPreparedAgainInsteadOfFailing(): void
    {
        [$verifier] = $this->verifier();
        try {
            assertSame('ok', $this->post($verifier)['status']);
            foreach (glob(sys_get_temp_dir() . '/forum-rewrite-gpg-keys-' . getmypid() . '-*/*', GLOB_ONLYDIR) ?: [] as $home) {
                exec('rm -rf ' . escapeshellarg($home));
            }
            assertSame('ok', $this->post($verifier)['status']);
        } finally {
            $verifier->releasePreparedKeys();
        }
    }

    public function testDefaultVerifierStillUsesOneHomePerCheck(): void
    {
        $before = $this->homes();
        $verifier = new OpenPgpSignatureVerifier();
        assertSame('ok', $this->post($verifier)['status']);
        assertSame($before, $this->homes());
    }

    /** @return array{OpenPgpSignatureVerifier,string} */
    private function verifier(): array
    {
        $log = sys_get_temp_dir() . '/forum-rewrite-gpg-reuse-log-' . bin2hex(random_bytes(6));
        $wrapper = $log . '.sh';
        file_put_contents($wrapper, "#!/bin/sh\necho run >> " . escapeshellarg($log) . "\nexec gpg \"\$@\"\n");
        chmod($wrapper, 0755);
        register_shutdown_function(static function () use ($log, $wrapper): void {
            @unlink($log);
            @unlink($wrapper);
        });

        return [new OpenPgpSignatureVerifier([$wrapper], reuseKeyHomes: true), $log];
    }

    private function post(OpenPgpSignatureVerifier $verifier): array
    {
        return $verifier->verifyDetached($this->fixture('public-key.asc'), $this->fixture('signed-post.txt'), $this->fixture('signed-post.txt.asc'), self::POST_SIGNER);
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/openpgp_signature/' . $name);
    }

    private function runs(string $log): int
    {
        return is_file($log) ? count(file($log) ?: []) : 0;
    }

    private function deadPid(): int
    {
        $process = proc_open(['true'], [], $pipes);
        $pid = proc_get_status($process)['pid'];
        proc_close($process);

        return $pid;
    }

    /** @return list<string> */
    private function homes(): array
    {
        $homes = array_merge(glob(sys_get_temp_dir() . '/forum-rewrite-gpg-verify-*') ?: [], glob(sys_get_temp_dir() . '/forum-rewrite-gpg-keys-*') ?: []);
        sort($homes);

        return $homes;
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Failed asserting identical values.');
        }
    }
}
