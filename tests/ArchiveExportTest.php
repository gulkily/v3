<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';
require_once __DIR__ . '/Support/ImportHttpServer.php';

use ForumRewrite\Images\ExportIdentity;
use ForumRewrite\Images\ImageBackup;

final class ArchiveExportTest
{
    public function testIdentityTracksCommittedRevisionDirtinessAndImagesNotPermissions(): void
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source');
        $w->put($root . '/records/posts/one.txt', $w->post('one'));
        $w->git($root);
        $revision = (new ImageBackup($root, $w->root . '/index.sqlite3'))->revision();
        $capture = fn (array $images = []) => ExportIdentity::capture($root, $revision, $images);

        $original = $capture();
        // Deterministic for identical committed state.
        assertSame($original, $capture());

        // A dirty working tree (uncommitted/untracked change present) yields a
        // distinct identity via the porcelain signal.
        file_put_contents($root . '/note.txt', 'one');
        $dirty = $capture();
        assertTrue($dirty !== $original);

        // Identity is committed-data-only: an untracked file's *content* change,
        // and file *permission* changes, do not by themselves alter identity —
        // only the presence of dirtiness (above) and committed content (revision)
        // and the image set do.
        unlink($root . '/note.txt');
        assertSame($original, $capture());
        chmod($root . '/records/posts/one.txt', 0600); clearstatcache();
        assertSame($original, $capture(), 'permissions are data-irrelevant and must not affect identity');

        // The image set (by content-addressed filename) still affects identity.
        assertTrue($capture(['abc123.png' => ['sha256' => 'abc123', 'bytes' => 10]]) !== $original);
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
        $damage = fopen($rebuilt['path'], 'r+b'); fwrite($damage, 'broken'); fclose($damage);
        $repaired = $backup->export('tar.gz'); assertSame(false, $repaired['reused']);
        assertSame(0, $w->command(['tar', '-tzf', $repaired['path']])[0]); fclose($repaired['stream']);
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

    public function testCleanupProtectsReadersAndRecoversAbandonedBuildsAndCapacity(): void
    {
        $w = new ImportTestWorkspace(); $root = $w->repository('source');
        $w->put($root . '/records/posts/one.txt', $w->post('one')); $w->git($root);
        $backup = new ImageBackup($root, $w->root . '/index.sqlite3');
        $old = $backup->export('tar.gz'); $oldBytes = file_get_contents($old['path']);
        $w->put($root . '/note.txt', 'changed');
        $next = $backup->export('tar.gz'); fclose($next['stream']);
        assertSame($oldBytes, stream_get_contents($old['stream'])); assertTrue(is_file($old['path']));
        fclose($old['stream']);
        $abandoned = dirname(dirname($old['path'])) . '/.build-' . str_repeat('a', 20);
        mkdir($abandoned); file_put_contents($abandoned . '/partial', 'partial');
        $zip = $backup->export('zip'); fclose($zip['stream']);
        assertSame(false, is_file($old['path'])); assertSame(false, is_dir($abandoned));
        $protected = $backup->export('zip');
        $limited = new \ForumRewrite\Images\ArchiveCache($w->root, $root, 1, 1);
        try {
            $limited->prepareBuild('different', 1);
            throw new RuntimeException('Expected capacity refusal');
        } catch (RuntimeException $e) { assertStringContains('capacity reached', $e->getMessage()); }
        assertTrue(is_file($protected['path'])); fclose($protected['stream']);
        $cache = new \ForumRewrite\Images\ArchiveCache($w->root, $root);
        $workspace = $cache->prepareBuild('different', 1);
        assertTrue(is_dir($workspace)); assertSame(false, is_file($protected['path']));
        ImageBackup::removeTree($workspace);
    }

    public function testChangesDuringCompressionAndKilledBuilderNeverPublishStaleOutput(): void
    {
        $w = new ImportTestWorkspace(); $root = $w->repository('source');
        $w->put($root . '/records/posts/one.txt', $w->post('one')); $w->git($root);
        $bin = $w->root . '/bin'; mkdir($bin);
        $ready = $w->root . '/ready'; $gate = $w->root . '/gate';
        $tar = trim($w->command(['which', 'tar'])[1]);
        file_put_contents($bin . '/tar', "#!/bin/sh\ntouch " . escapeshellarg($ready)
            . "\nwhile [ ! -f " . escapeshellarg($gate) . " ]; do sleep 0.02; done\nexec " . escapeshellarg($tar) . ' "$@"' . "\n");
        chmod($bin . '/tar', 0700);
        $script = $w->root . '/build.php';
        file_put_contents($script, '<?php require ' . var_export(dirname(__DIR__) . '/autoload.php', true)
            . '; try { $d=(new ForumRewrite\\Images\\ImageBackup($argv[1],$argv[2]))->export("tar.gz"); echo "published"; fclose($d["stream"]); } catch (ForumRewrite\\Images\\ArchiveConflictException $e) { echo "conflict"; }');
        foreach ([false, true] as $kill) {
            @unlink($ready); @unlink($gate);
            $pipes = [];
            $process = proc_open(['env', 'PATH=' . $bin . ':' . getenv('PATH'), PHP_BINARY, $script, $root, $w->root . '/index.sqlite3'],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            try {
                $deadline = microtime(true) + 5;
                while (!is_file($ready) && microtime(true) < $deadline) { usleep(10000); clearstatcache(); }
                assertTrue(is_file($ready));
                if ($kill) { proc_terminate($process, 9); }
                else {
                    (new \ForumRewrite\Support\ExecutionLock($w->root . '/forum-rewrite.lock'))->withExclusiveLock(function () use ($w, $root): void {
                        unlink($root . '/records/posts/one.txt'); $w->git($root);
                    });
                }
            } finally { touch($gate); }
            $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
            if (!$kill) assertSame('conflict', $out);
            assertSame('', $err);
            assertSame([], glob($w->root . '/archive-exports/*/metadata'));
        }
        $backup = new ImageBackup($root, $w->root . '/index.sqlite3');
        $recovered = $backup->export('tar.gz'); fclose($recovered['stream']);
        assertSame([], glob($w->root . '/archive-exports/.build-*'));
        [$status, $listing] = $w->command(['tar', '-tzf', $recovered['path']]);
        assertSame(0, $status); assertStringNotContains('one.txt', $listing);
    }

    public function testStoragePathsAndInsufficientFilesystemReservation(): void
    {
        $w = new ImportTestWorkspace(); $root = $w->repository('source');
        $w->put($root . '/records/posts/one.txt', $w->post('one')); $w->git($root);
        try {
            new \ForumRewrite\Images\ArchiveCache($root . '/cache', $root);
            throw new RuntimeException('Expected unsafe storage refusal');
        } catch (RuntimeException $e) { assertStringContains('outside content', $e->getMessage()); }
        assertSame(false, is_dir($root . '/cache'));
        $oldDirectory = getcwd();
        try {
            chdir($w->root);
            $backup = new ImageBackup($root, 'cache/index.sqlite3');
            $export = $backup->export('tar.gz');
            assertTrue(str_starts_with($export['path'], '/'));
            fclose($export['stream']);
        } finally { chdir($oldDirectory); }
        $cache = new \ForumRewrite\Images\ArchiveCache($w->root . '/space', $root, PHP_INT_MAX);
        // Request more than actual filesystem availability without allocating/filling shared disk.
        try {
            $cache->prepareBuild('identity', (int) disk_free_space($cache->root));
            throw new RuntimeException('Expected free-space refusal');
        } catch (RuntimeException $e) { assertStringContains('free disk space', $e->getMessage()); }
        $retry = $cache->prepareBuild('identity', 0); assertTrue(is_dir($retry));
        ImageBackup::removeTree($retry);
    }

    public function testInstanceDownloaderRetriesRealBusySourceWithoutPartialDestination(): void
    {
        $w = new ImportTestWorkspace(); $root = $w->repository('source', true); $w->git($root);
        mkdir($w->root . '/cache');
        $server = new ImportHttpServer(realpath(__DIR__ . '/../public/router.php'), $w->root . '/server.log', [
            'FORUM_REPOSITORY_ROOT' => $root, 'FORUM_DATABASE_PATH' => $w->root . '/cache/index.sqlite3',
            'FORUM_STATIC_HTML_ROOT' => $w->root . '/static', 'FORUM_APPROVED_MEMBERS_ONLY' => 'false',
        ]);
        $held = fopen($w->root . '/cache/image-export.lock', 'c+'); flock($held, LOCK_EX);
        $downloader = new \ForumRewrite\Import\InstanceArchiveDownloader(); $path = $w->root . '/download.tar.gz';
        try {
            $downloader->download($server->url, $path);
            throw new RuntimeException('Expected busy source refusal');
        } catch (RuntimeException $e) {
            assertStringContains('HTTP 503', $e->getMessage()); assertStringContains('Retry the download', $e->getMessage());
            assertSame(false, is_file($path));
        } finally { flock($held, LOCK_UN); fclose($held); }
        assertTrue($downloader->download($server->url, $path) > 0);
        assertSame(0, $w->command(['tar', '-tzf', $path])[0]);
    }

}
