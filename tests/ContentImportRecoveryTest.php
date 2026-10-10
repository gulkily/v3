<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Import\ContentImportRunner;

final class ContentImportRecoveryTest
{
    public function testRecoveryAtEachWriteBoundaryCreatesOnlyOneCommit(): void
    {
        foreach (['copied', 'staged', 'committed', 'published'] as $failurePhase) {
            $w = new ImportTestWorkspace();
            $source = $w->repository('source');
            $target = $w->repository('target');
            $w->git($target);
            $w->put($source . '/records/posts/root.txt', $w->post('root'));
            $database = $w->root . '/cache/index.sqlite3';
            $runner = new ContentImportRunner($target, $database, static function ($phase) use ($failurePhase): void {
                if ($phase === $failurePhase) {
                    throw new RuntimeException('Injected interruption');
                }
            });
            try {
                $runner->run($source, static fn () => null);
                throw new LogicException('Expected interruption');
            } catch (RuntimeException $error) {
                assertSame('Injected interruption', $error->getMessage());
            }
            $calls = 0;
            $result = (new ContentImportRunner($target, $database))->run(null, static function () use (&$calls): void { $calls++; });
            assertSame(1, $calls);
            assertSame('complete', $result['status']);
            assertSame('2', trim($w->command(['git', '-C', $target, 'rev-list', '--count', 'HEAD'])[1]));
            assertSame('', trim($w->command(['git', '-C', $target, 'status', '--porcelain'])[1]));
            assertTrue(!file_exists($target . '/.git/instance-import/pending.json'));
        }
    }

    public function testRecoveryPreservesDivergentLocalEdits(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        $target = $w->repository('target');
        $w->git($target);
        $w->put($source . '/records/posts/root.txt', $w->post('root'));
        $database = $w->root . '/cache/index.sqlite3';
        try {
            (new ContentImportRunner($target, $database, static function (): void { throw new RuntimeException('stop'); }))->run($source, static fn () => null);
        } catch (RuntimeException) {
        }
        $w->put($target . '/records/posts/root.txt', 'Local edit after interruption');
        $failed = false;
        try {
            (new ContentImportRunner($target, $database))->run(null, static fn () => null);
        } catch (RuntimeException) {
            $failed = true;
        }
        assertTrue($failed);
        assertSame('Local edit after interruption', file_get_contents($target . '/records/posts/root.txt'));
    }

    public function testPreviewAndRepeatAndSharedWriteLock(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        $target = $w->repository('target');
        $w->git($target);
        $w->put($source . '/records/posts/root.txt', $w->post('root'));
        $database = $w->root . '/cache/index.sqlite3';
        $runner = new ContentImportRunner($target, $database);
        $runner->run($source, static function (): void { throw new LogicException('Preview must not publish'); }, true);
        assertTrue(!file_exists($target . '/records/posts/root.txt'));
        $lock = fopen(dirname($database) . '/forum-rewrite.lock', 'r+');
        flock($lock, LOCK_EX);
        $oldTimeout = getenv('FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS');
        putenv('FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS=0');
        try {
            $failed = false;
            try { $runner->run($source, static fn () => null); } catch (RuntimeException) { $failed = true; }
            assertTrue($failed);
            assertTrue(!file_exists($target . '/records/posts/root.txt'));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            putenv($oldTimeout === false ? 'FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS' : 'FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS=' . $oldTimeout);
        }
        $runner->run($source, static fn () => null);
        $calls = 0;
        $result = $runner->run($source, static function () use (&$calls): void { $calls++; });
        assertSame(0, $result['counts']['import']);
        assertSame(1, $result['counts']['duplicate']);
        assertSame(1, $calls);
        assertSame('2', trim($w->command(['git', '-C', $target, 'rev-list', '--count', 'HEAD'])[1]));
    }
}
