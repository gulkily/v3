<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\ExchangeRetractionState;

final class AbsentRetractionSnapshotTest
{
    private function fixture(string $id = 'target'): string
    {
        $root = sys_get_temp_dir() . '/absent-snapshot-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0700, true);
        mkdir($root . '/records/post-retractions');
        file_put_contents($root . '/records/post-retractions/post-retraction-' . $id . '.txt',
            "Record-ID: post-retraction-$id\nCreated-At: 2026-10-14T12:00:00Z\nPost-ID: $id\nOperation: retract\nAuthor-Identity-ID: openpgp:" . str_repeat('a', 40) . "\n\n");
        return $root;
    }

    public function publicationFixture(string $target = 'target'): array
    {
        $root = $this->fixture($target);
        $fixture = __DIR__ . '/fixtures/parity_minimal_v1';
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = $root . substr($file->getPathname(), strlen($fixture));
            if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
            copy($file->getPathname(), $path);
        }
        // Build before deletion to deliberately retain stale target content in the source model.
        $recordPath = $root . '/records/post-retractions/post-retraction-' . $target . '.txt';
        $record = file_get_contents($recordPath); unlink($recordPath);
        $rootId = 'root-001';
        if ($target !== 'root-001') file_put_contents($root . '/records/posts/' . $target . '.txt',
            "Post-ID: $target\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: general\nThread-ID: $rootId\nParent-ID: $rootId\nAuthor-Identity-ID: openpgp:" . str_repeat('a', 40) . "\n\nABSENT-SECRET\n");
        $targetPath = $root . '/records/posts/' . $target . '.txt';
        $bytes = file_get_contents($targetPath);
        if (!str_contains($bytes, 'Author-Identity-ID:')) {
            $bytes = preg_replace('/\n/', "\nAuthor-Identity-ID: openpgp:" . str_repeat('a', 40) . "\n", $bytes, 1);
            file_put_contents($targetPath, $bytes);
        }
        $post = (new \ForumRewrite\Canonical\PostRecordParser())->parse($bytes);
        $accepted = \ForumRewrite\Canonical\AcceptedRetraction::prepare($post, (new \ForumRewrite\Canonical\PostRetractionRecordParser())->parse($record), $record, '', 'author', hash('sha256', $bytes));
        $database = $root . '/source.sqlite3';
        (new \ForumRewrite\ReadModel\ReadModelBuilder($root, $database, new \ForumRewrite\Canonical\CanonicalRecordRepository($root), 'absent_test'))->rebuild();
        unlink($root . '/records/posts/' . $target . '.txt');
        file_put_contents($root . '/' . \ForumRewrite\Canonical\AcceptedRetraction::path($target), \ForumRewrite\Canonical\AcceptedRetraction::encode($accepted));
        return [$root, $database];
    }

    public function testStaleSnapshotIsAllowedAndRefreshRemovesDeletedContent(): void
    {
        [$root, $database] = $this->publicationFixture();
        $before = hash_file('sha256', $database);
        $stale = (new \ForumRewrite\Offline\OfflineSnapshotPublisher($root . '/stale'))->publish($database);
        assertSame(true, str_contains(file_get_contents($stale['path']), 'ABSENT-SECRET'));
        (new \ForumRewrite\ReadModel\ReadModelBuilder($root, $database, new \ForumRewrite\Canonical\CanonicalRecordRepository($root)))->rebuild();
        $before = hash_file('sha256', $database);
        $result = (new \ForumRewrite\Offline\OfflineSnapshotPublisher($root . '/static'))->publish($database);
        $snapshot = new PDO('sqlite:' . $result['path']);
        assertSame(0, (int) $snapshot->query("SELECT COUNT(*) FROM posts WHERE post_id='target'")->fetchColumn());
        assertSame(1, (int) $snapshot->query("SELECT COUNT(*) FROM posts WHERE post_id='reply-001'")->fetchColumn());
        assertSame(false, str_contains(file_get_contents($result['path']), 'ABSENT-SECRET'));
        assertSame($before, hash_file('sha256', $database));
        assertTrue(is_file($root . '/records/post-retractions/post-retraction-target.json'));
        assertSame(false, is_file($result['path'] . '.retraction-generation'));
    }

    public function testAbsentRootRetainsRepliesWithContentFreeThread(): void
    {
        [$root, $database] = $this->publicationFixture('root-001');
        (new \ForumRewrite\ReadModel\ReadModelBuilder($root, $database, new \ForumRewrite\Canonical\CanonicalRecordRepository($root)))->rebuild();
        $result = (new \ForumRewrite\Offline\OfflineSnapshotPublisher($root . '/static'))->publish($database);
        $snapshot = new PDO('sqlite:' . $result['path']);
        assertSame(0, (int) $snapshot->query("SELECT COUNT(*) FROM posts WHERE post_id='root-001'")->fetchColumn());
        assertSame(1, (int) $snapshot->query("SELECT COUNT(*) FROM posts WHERE post_id='reply-001'")->fetchColumn());
        assertSame('', $snapshot->query("SELECT body_preview FROM threads WHERE root_post_id='root-001'")->fetchColumn());
        assertSame(null, $snapshot->query("SELECT subject FROM threads WHERE root_post_id='root-001'")->fetchColumn());
    }

    public function testActualReaderAppliesExclusionsOutsideUpdateThreads(): void
    {
        foreach (['target', 'root-001'] as $target) {
            [$root, $database] = $this->publicationFixture($target);
            $builder = new \ForumRewrite\Offline\PublicOfflineSnapshotBuilder();
            $builder->build($database, $root . '/base.sqlite3');
            (new \ForumRewrite\ReadModel\ReadModelBuilder($root, $database, new \ForumRewrite\Canonical\CanonicalRecordRepository($root)))->rebuild();
            $builder->buildUpdate($database, $root . '/update.sqlite3');
            $update = new PDO('sqlite:' . $root . '/update.sqlite3');
            assertSame([$target], $update->query('SELECT post_id FROM excluded_posts')->fetchAll(PDO::FETCH_COLUMN));
            if ($target === 'target') {
                assertSame(false, str_contains(file_get_contents($root . '/update.sqlite3'), 'ABSENT-SECRET'));
                assertSame(false, str_contains(file_get_contents($root . '/update.sqlite3'), 'DEPENDENT-EXPANSION-SECRET'));
            }
            // Exercise the bounded-window gap: no replacement of this base thread.
            $update->exec("DELETE FROM threads WHERE root_post_id='root-001'; DELETE FROM posts WHERE thread_id='root-001'");
            $script = <<<'JS'
const fs = require('fs'), vm = require('vm');
const project = process.argv[2], root = process.argv[3], target = process.argv[4];
global.window = {}; global.document = {addEventListener(){}};
vm.runInThisContext(fs.readFileSync(project + '/public/assets/offline_reader.js', 'utf8'));
require(project + '/public/assets/sql-wasm.js')({locateFile: f => project + '/public/assets/' + f}).then(SQL => {
  const base = new SQL.Database(fs.readFileSync(root + '/base.sqlite3'));
  const update = new SQL.Database(fs.readFileSync(root + '/update.sqlite3'));
  window.forumOfflineSnapshot.applyUpdate(base, update);
  window.forumOfflineSnapshot.applyUpdate(base, update);
  const count = id => base.exec('SELECT COUNT(*) FROM posts WHERE post_id = ?', [id])[0].values[0][0];
  if (count(target) !== 0 || count('expansion') !== 0 || count('reply-001') !== 1) throw Error('exclusion or surviving reply failed');
  const thread = base.exec("SELECT subject, body_preview FROM threads WHERE root_post_id='root-001'")[0].values[0];
  if (target === 'root-001' && (thread[0] !== null || thread[1] !== '')) throw Error('stale root presentation');
  update.run('DROP TABLE excluded_posts');
  window.forumOfflineSnapshot.applyUpdate(base, update); // Legacy artifacts still work.
  console.log('ok');
}).catch(error => { console.error(error); process.exit(1); });
JS;
            file_put_contents($root . '/consume.cjs', $script);
            exec('node ' . escapeshellarg($root . '/consume.cjs') . ' ' . escapeshellarg(dirname(__DIR__)) . ' ' . escapeshellarg($root) . ' ' . escapeshellarg($target) . ' 2>&1', $output, $code);
            assertSame(0, $code, implode("\n", $output));
        }
    }


}
