<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\IncrementalReadModelUpdater;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Security\ReactionSignature;
use ForumRewrite\Security\SignatureCheckCache;

final class SignatureCheckCacheTest
{
    private const AUTHOR = 'openpgp:15c2ef95baee78f4c635a43323b6ae7ec7af9919';

    public function testSuccessfulCheckIsReusedAcrossProcessesWithoutGpg(): void
    {
        [$root, $cacheDir, $log] = $this->workspace();
        try {
            SignatureCheckCache::useBeside($cacheDir . '/post_index.sqlite3');
            assertSame('verified', $this->status($root, 'records/posts/signed-test.txt'));
            assertSame(true, $this->gpgRuns($log) > 0);
            assertSame(true, is_file($cacheDir . '/signature_checks.sqlite3'));

            // A new request or rebuild starts with an empty memory cache.
            $before = $this->gpgRuns($log);
            $this->forgetMemory();
            SignatureCheckCache::disable();
            SignatureCheckCache::useBeside($cacheDir . '/post_index.sqlite3');
            assertSame('verified', $this->status($root, 'records/posts/signed-test.txt'));
            assertSame($before, $this->gpgRuns($log));
        } finally {
            $this->cleanup($root, $cacheDir);
        }
    }

    public function testChangedRecordMissesAndFailuresAreNotKept(): void
    {
        [$root, $cacheDir, $log] = $this->workspace();
        try {
            SignatureCheckCache::useBeside($cacheDir . '/post_index.sqlite3');
            assertSame('verified', $this->status($root, 'records/posts/signed-test.txt'));
            $contents = (string) file_get_contents($root . '/records/posts/signed-test.txt');
            file_put_contents($root . '/records/posts/signed-test.txt', str_replace('signed-test', 'altered-test', $contents));

            $before = $this->gpgRuns($log);
            $this->forgetMemory();
            assertSame('invalid', $this->status($root, 'records/posts/signed-test.txt'));
            assertSame(true, $this->gpgRuns($log) > $before);

            // A failed check runs again: a GPG that could not run looks like a bad signature.
            $before = $this->gpgRuns($log);
            $this->forgetMemory();
            assertSame('invalid', $this->status($root, 'records/posts/signed-test.txt'));
            assertSame(true, $this->gpgRuns($log) > $before);
            assertSame(1, (int) (new PDO('sqlite:' . $cacheDir . '/signature_checks.sqlite3'))->query('SELECT COUNT(*) FROM signature_checks')->fetchColumn());
        } finally {
            $this->cleanup($root, $cacheDir);
        }
    }

    public function testMissingOrUnusableCacheFallsBackToGpg(): void
    {
        [$root, $cacheDir, $log] = $this->workspace();
        try {
            SignatureCheckCache::useBeside($cacheDir . '/missing-directory/post_index.sqlite3');
            assertSame('verified', $this->status($root, 'records/posts/signed-test.txt'));
            assertSame(false, is_dir($cacheDir . '/missing-directory'));

            mkdir($cacheDir . '/signature_checks.sqlite3');
            SignatureCheckCache::useBeside($cacheDir . '/post_index.sqlite3');
            $before = $this->gpgRuns($log);
            $this->forgetMemory();
            assertSame('verified', $this->status($root, 'records/posts/signed-test.txt'));
            assertSame(true, $this->gpgRuns($log) > $before);
        } finally {
            @rmdir($cacheDir . '/signature_checks.sqlite3');
            $this->cleanup($root, $cacheDir);
        }
    }

    public function testRebuildIncrementalUpdatesAndRequestsUseTheCacheBesideTheReadModel(): void
    {
        [$root, $cacheDir] = $this->workspace();
        try {
            $entries = [
                'builder' => static fn (string $database) => new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)),
                'incremental' => static fn (string $database) => new IncrementalReadModelUpdater($database, $root),
                'application' => static fn (string $database) => new Application(dirname(__DIR__), $root, $database),
            ];
            foreach ($entries as $name => $create) {
                $directory = $cacheDir . '/' . $name;
                mkdir($directory);
                SignatureCheckCache::disable();
                $this->forgetMemory();
                $create($directory . '/post_index.sqlite3');
                assertSame('verified', $this->status($root, 'records/posts/signed-test.txt'));
                assertSame(true, is_file($directory . '/signature_checks.sqlite3'));
            }
        } finally {
            $this->cleanup($root, $cacheDir);
        }
    }

    /** @return array{string,string,string} */
    private function workspace(): array
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-signature-cache-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0777, true);
        mkdir($root . '/records/public-keys', 0777, true);
        $fixtures = __DIR__ . '/fixtures/openpgp_signature/';
        copy($fixtures . 'public-key.asc', $root . '/records/public-keys/openpgp-15C2EF95BAEE78F4C635A43323B6AE7EC7AF9919.asc');
        copy($fixtures . 'signed-post.txt', $root . '/records/posts/signed-test.txt');
        copy($fixtures . 'signed-post.txt.asc', $root . '/records/posts/signed-test.txt.asc');

        // Count GPG runs with a wrapper that logs each call and runs the real gpg.
        $cacheDir = $root . '-state';
        mkdir($cacheDir . '/bin', 0777, true);
        $log = $cacheDir . '/gpg.log';
        $realGpg = trim((string) shell_exec('command -v gpg'));
        file_put_contents($cacheDir . '/bin/gpg', "#!/bin/sh\necho run >> " . escapeshellarg($log) . "\nexec " . escapeshellarg($realGpg) . " \"\$@\"\n");
        chmod($cacheDir . '/bin/gpg', 0755);
        $this->path = (string) getenv('PATH');
        putenv('PATH=' . $cacheDir . '/bin:' . $this->path);
        SignatureCheckCache::disable();
        $this->forgetMemory();

        return [$root, $cacheDir, $log];
    }

    private string $path = '';

    private function status(string $root, string $path): string
    {
        return ReactionSignature::status($root, $path, (string) file_get_contents($root . '/' . $path), self::AUTHOR);
    }

    private function gpgRuns(string $log): int
    {
        return is_file($log) ? count(file($log) ?: []) : 0;
    }

    private function forgetMemory(): void
    {
        (new ReflectionProperty(ReactionSignature::class, 'cache'))->setValue(null, []);
    }

    private function cleanup(string $root, string $cacheDir): void
    {
        putenv('PATH=' . $this->path);
        SignatureCheckCache::disable();
        $this->forgetMemory();
        exec('rm -rf ' . escapeshellarg($root) . ' ' . escapeshellarg($cacheDir));
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
