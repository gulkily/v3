<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Images\ExportIdentity;
use ForumRewrite\Images\ImageBackup;

final class ArchiveExportTest
{
    public function testIdentityIncludesUntrackedBytesPermissionsAndImages(): void
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source');
        $w->put($root . '/records/posts/one.txt', $w->post('one'));
        $w->git($root);
        $revision = (new ImageBackup($root, $w->root . '/index.sqlite3'))->revision();
        $capture = fn (array $images = []) => ExportIdentity::capture($root, $revision, $images);
        $original = $capture();
        assertSame($original, $capture());
        file_put_contents($root . '/note.txt', 'one');
        $first = $capture();
        assertTrue($first !== $original);
        $mtime = filemtime($root . '/note.txt');
        file_put_contents($root . '/note.txt', 'two'); touch($root . '/note.txt', $mtime);
        assertTrue($capture() !== $first);
        unlink($root . '/note.txt');
        assertSame($original, $capture());
        assertTrue($capture(['image' => ['sha256' => 'changed']]) !== $original);
        chmod($root . '/records/posts/one.txt', 0600); clearstatcache();
        assertTrue($capture() !== $original);
    }
    public function testWarmArchivesReuseAndCorruptionRebuildsWithoutDeletingReaders(): void
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source');
        $w->put($root . '/records/posts/one.txt', $w->post('one')); $w->git($root);
        $backup = new ImageBackup($root, $w->root . '/index.sqlite3');
        foreach (['tar.gz', 'zip', 'images'] as $kind) {
            $first = $backup->export($kind);
            $second = $backup->export($kind);
            assertSame(false, $first['reused']); assertSame(true, $second['reused']);
            assertSame($first['path'], $second['path']);
            assertSame(stream_get_contents($first['stream']), stream_get_contents($second['stream']));
            fclose($first['stream']); fclose($second['stream']);
        }
        $old = $backup->export('tar.gz');
        $bytes = file_get_contents($old['path']);
        file_put_contents(dirname($old['path']) . '/metadata', 'broken');
        $rebuilt = $backup->export('tar.gz');
        assertSame(false, $rebuilt['reused']);
        assertSame($bytes, stream_get_contents($old['stream']));
        assertTrue(is_file($rebuilt['path']));
        fclose($old['stream']); fclose($rebuilt['stream']);
        $w->put($root . '/note.txt', 'new bytes without a commit');
        $changed = $backup->export('tar.gz');
        assertSame(false, $changed['reused']);
        assertTrue($changed['path'] !== $rebuilt['path']);
        fclose($changed['stream']);
    }

    public function testSharedLockDeadlineAndWarmHitDuringAnotherBuild(): void
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source');
        $w->put($root . '/records/posts/one.txt', $w->post('one')); $w->git($root);
        $backup = new ImageBackup($root, $w->root . '/index.sqlite3');
        $first = $backup->export('tar.gz'); fclose($first['stream']);
        $held = fopen($w->root . '/image-export.lock', 'c+'); flock($held, LOCK_EX);
        $start = microtime(true);
        $warm = $backup->export('tar.gz');
        assertTrue($warm['reused']); assertTrue(microtime(true) - $start < 1);
        fclose($warm['stream']);
        $locks = new \ForumRewrite\Images\ArchiveLocks($w->root, 0.12);
        try {
            $locks->withExclusiveLock('image-export.lock', fn () => true);
            throw new RuntimeException('Expected busy.');
        } catch (\ForumRewrite\Images\ArchiveBusyException $e) {
            assertTrue(microtime(true) - $start >= 0.12);
            $next = microtime(true);
            try { $locks->withExclusiveLock('image-export.lock', fn () => true); }
            catch (\ForumRewrite\Images\ArchiveBusyException) {}
            assertTrue(microtime(true) - $next < 0.1);
        } finally { fclose($held); }
        $zip = $backup->export('zip'); fclose($zip['stream']);
    }

    public function testConcurrentCompatibleRequestsProduceOneArtifact(): void
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source');
        $w->put($root . '/records/posts/one.txt', $w->post('one')); $w->git($root);
        $held = fopen($w->root . '/image-export.lock', 'c+'); flock($held, LOCK_EX);
        $script = $w->root . '/download.php';
        file_put_contents($script, '<?php require ' . var_export(dirname(__DIR__) . '/autoload.php', true)
            . '; $b = new ForumRewrite\\Images\\ImageBackup($argv[1], $argv[2]); $d = $b->export($argv[3]); echo ($d["reused"] ? "hit" : "built"); fclose($d["stream"]);');
        $jobs = [];
        foreach (['tar.gz', 'tar.gz', 'zip'] as $kind) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, $script, $root, $w->root . '/index.sqlite3', $kind], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $jobs[] = [$process, $pipes];
        }
        usleep(200000); flock($held, LOCK_UN); fclose($held);
        $results = [];
        foreach ($jobs as [$process, $pipes]) {
            $results[] = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); assertSame(0, proc_close($process), $error);
        }
        assertSame(['built', 'hit'], (function ($v) { sort($v); return $v; })(array_slice($results, 0, 2)));
        assertSame('built', $results[2]);
        assertSame(2, count(glob($w->root . '/archive-exports/*/metadata')));
    }

    public function testBusyConflictAndFailureResponsesDoNotLeakPathsAndRetrySucceeds(): void
    {
        $w = new ImportTestWorkspace(); $root = $w->repository('source', true); $w->git($root);
        $database = $w->root . '/index.sqlite3';
        (new \ForumRewrite\ReadModel\ReadModelBuilder($root, $database, new \ForumRewrite\Canonical\CanonicalRecordRepository($root)))->rebuild();
        $code = 'require $argv[1]."/autoload.php"; (new ForumRewrite\\Application($argv[1],$argv[2],$argv[3]))->handle("GET",$argv[4]); fwrite(STDOUT,"\nHTTP_STATUS=".http_response_code());';
        $request = fn ($query = '') => $w->command(['env', 'FORUM_APPROVED_MEMBERS_ONLY=false',
            'FORUM_IMAGE_ROOT=' . $w->root . '/no-images', 'php', '-d', 'error_log=' . $w->root . '/errors.log', '-r', $code,
            dirname(__DIR__), $root, $database, '/downloads/repository.tar.gz' . $query]);
        $held = fopen($w->root . '/image-export.lock', 'c+'); flock($held, LOCK_EX);
        try {
            $start = microtime(true); [$status, $body] = $request();
            assertSame(0, $status); assertStringContains('HTTP_STATUS=503', $body);
            assertStringContains('Retry the download', $body); assertStringNotContains($w->root, $body);
            assertTrue(microtime(true) - $start < 7);
        } finally { flock($held, LOCK_UN); fclose($held); }
        [$status, $body] = $request(); assertSame(0, $status); assertTrue(str_starts_with($body, "\x1f\x8b"));
        [$status, $body] = $request('?revision=' . str_repeat('0', 40));
        assertStringContains('HTTP_STATUS=409', $body); assertStringContains('Refresh Backup', $body);
        $w->put($root . '/records/posts/broken.txt', 'uncommitted');
        [$status, $body] = $request();
        assertStringContains('HTTP_STATUS=500', $body); assertStringNotContains($w->root, $body);
        assertStringContains('source integrity', $body);
        assertStringContains('uncommitted changes', file_get_contents($w->root . '/errors.log'));
        unlink($root . '/records/posts/broken.txt');
        [$status, $body] = $request(); assertTrue(str_starts_with($body, "\x1f\x8b"));
    }

}
