<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Import\ContentImportRunner;
use ForumRewrite\Import\ImportedContentPublisher;
use ForumRewrite\Offline\OfflineSnapshotLocator;

final class ImportedContentPublicationTest
{
    public function testEachPublicationFailureResumesWithoutNewRecords(): void
    {
        foreach (['before_build', 'built', 'promoted', 'activated', 'offline_snapshot'] as $phase) {
            $w = new ImportTestWorkspace();
            $source = $w->repository('source');
            $target = $w->repository('target', true);
            $w->git($target);
            $w->put($source . '/records/posts/imported.txt', $w->post('imported'));
            $database = $w->root . '/cache/index.sqlite3';
            $static = $w->root . '/static';
            $runner = new ContentImportRunner($target, $database);
            $broken = new ImportedContentPublisher(dirname(__DIR__), $target, $database, $static, checkpoint: static function ($at) use ($phase): void {
                if ($at === $phase) {
                    throw new RuntimeException('Injected publication failure');
                }
            });
            try {
                $runner->run($source, $broken->publishWhileLocked(...));
                throw new LogicException('Expected failure');
            } catch (RuntimeException $error) {
                assertSame('Injected publication failure', $error->getMessage());
            }
            assertTrue(is_file($target . '/.git/instance-import/pending.json'));
            $publisher = new ImportedContentPublisher(dirname(__DIR__), $target, $database, $static);
            $result = $runner->run(null, $publisher->publishWhileLocked(...));
            assertSame('complete', $result['status']);
            $pdo = new PDO('sqlite:' . $database);
            assertSame('Imported imported', $pdo->query("SELECT subject FROM posts WHERE post_id = 'imported'")->fetchColumn());
            $pdo = null;
            assertTrue(!file_exists($static . '/current'));
            $locator = new OfflineSnapshotLocator();
            assertSame($static . '/offline/snapshot.sqlite3', $locator->servedSnapshotPath($static));
            $snapshot = new PDO('sqlite:' . $locator->servedSnapshotPath($static));
            assertSame('imported', $snapshot->query("SELECT post_id FROM posts WHERE post_id='imported'")->fetchColumn());
            $snapshot = null;
            assertTrue(is_file($static . '/offline/update.sqlite3'));
            assertSame('2', trim($w->command(['git', '-C', $target, 'rev-list', '--count', 'HEAD'])[1]));
        }
    }

    public function testImportRetiresStaleReleaseAndDirectHtmlBeforeRefreshingOfflineData(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        $target = $w->repository('target', true);
        $w->git($target);
        $w->put($source . '/records/posts/fresh.txt', $w->post('fresh'));
        $database = $w->root . '/cache/index.sqlite3';
        $static = $w->root . '/static';
        $w->put($static . '/releases/old/index.html', 'stale release');
        symlink('releases/old', $static . '/current');
        $w->put($static . '/tags/general.html', 'stale direct page');
        $w->put($static . '/offline/snapshot.sqlite3', 'stale offline');
        // An incomplete legacy library must not be traversed or removed by import.
        $w->put($static . '/docs/guide.md.html', 'retained docs');
        $w->put($static . '/docs.html', 'retained docs alias');
        $publisher = new ImportedContentPublisher(dirname(__DIR__), $target, $database, $static);
        (new ContentImportRunner($target, $database))->run($source, $publisher->publishWhileLocked(...));
        assertTrue(!is_link($static . '/current'));
        assertTrue(!is_file($static . '/tags/general.html'));
        assertSame('stale release', file_get_contents($static . '/releases/old/index.html'));
        assertSame('retained docs', file_get_contents($static . '/docs/guide.md.html'));
        assertSame('retained docs alias', file_get_contents($static . '/docs.html'));
        $snapshot = new PDO('sqlite:' . $static . '/offline/snapshot.sqlite3');
        assertSame('Imported fresh', $snapshot->query("SELECT subject FROM posts WHERE post_id='fresh'")->fetchColumn());
    }

    public function testPrivateDestinationDoesNotPublishPublicArtifacts(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        $target = $w->repository('target', true);
        $w->git($target);
        $w->put($source . '/records/posts/imported.txt', $w->post('imported'));
        $database = $w->root . '/cache/index.sqlite3';
        $static = $w->root . '/static';
        $old = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        putenv('FORUM_APPROVED_MEMBERS_ONLY=true');
        try {
            $publisher = new ImportedContentPublisher(dirname(__DIR__), $target, $database, $static);
            (new ContentImportRunner($target, $database))->run($source, $publisher->publishWhileLocked(...));
            assertTrue(is_file($database));
            assertTrue(!file_exists($static . '/current'));
            assertTrue(!file_exists($static . '/offline/snapshot.sqlite3'));
        } finally {
            putenv($old === false ? 'FORUM_APPROVED_MEMBERS_ONLY' : 'FORUM_APPROVED_MEMBERS_ONLY=' . $old);
        }
    }
}
