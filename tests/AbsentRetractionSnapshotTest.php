<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\ExchangeRetractionState;
use ForumRewrite\Retraction\PublicationGuard;

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

    public function testSnapshotAbsenceIsNotGlobalAuthorityAndReappearanceIsBuildLocal(): void
    {
        foreach (['target', 'reply-20260526032118-654b2b08'] as $id) {
            $root = $this->fixture($id);
            assertSame(null, ExchangeRetractionState::read($root));
            $state = ExchangeRetractionState::snapshot($root);
            assertSame([$id => true], $state['absent']);
            assertSame([], $state['ids']);
            $generation = PublicationGuard::snapshotGeneration($root, $state['absent']);
            file_put_contents($root . '/records/posts/' . $id . '.txt', "Post-ID: $id\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: general\nAuthor-Identity-ID: openpgp:" . str_repeat('b', 40) . "\n\nReappeared\n");
            assertSame($generation, PublicationGuard::snapshotGeneration($root, $state['absent']));
            assertSame([], ExchangeRetractionState::snapshot($root)['absent']);
            assertSame([], ExchangeRetractionState::read($root));
            $recordPath = $root . '/records/post-retractions/post-retraction-' . $id . '.txt';
            file_put_contents($recordPath, str_replace('12:00:00Z', '12:01:00Z', file_get_contents($recordPath)));
            assertTrue($generation !== PublicationGuard::snapshotGeneration($root, $state['absent']));
        }
    }

    public function testUnsafeAbsenceAndMalformedEvidenceRemainUnavailable(): void
    {
        $root = $this->fixture();
        symlink('/nonexistent-absent-snapshot', $root . '/records/posts/target.txt');
        assertSame(null, ExchangeRetractionState::snapshot($root));
        unlink($root . '/records/posts/target.txt');
        file_put_contents($root . '/records/post-retractions/post-retraction-target.txt', 'bad');
        assertSame(null, ExchangeRetractionState::snapshot($root));
        unlink($root . '/records/post-retractions/post-retraction-target.txt');
        rmdir($root . '/records/post-retractions');
        symlink('/nonexistent-retractions', $root . '/records/post-retractions');
        assertSame(null, ExchangeRetractionState::snapshot($root));
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
        $database = $root . '/source.sqlite3';
        (new \ForumRewrite\ReadModel\ReadModelBuilder($root, $database, new \ForumRewrite\Canonical\CanonicalRecordRepository($root), 'absent_test'))->rebuild();
        unlink($root . '/records/posts/' . $target . '.txt');
        file_put_contents($recordPath, $record);
        return [$root, $database];
    }

    public function testBasePublicationFiltersStaleReplyAndRetainsSource(): void
    {
        [$root, $database] = $this->publicationFixture();
        $before = hash_file('sha256', $database);
        $result = (new \ForumRewrite\Offline\OfflineSnapshotPublisher($root . '/static'))->publish($database);
        $snapshot = new PDO('sqlite:' . $result['path']);
        assertSame(0, (int) $snapshot->query("SELECT COUNT(*) FROM posts WHERE post_id='target'")->fetchColumn());
        assertSame(1, (int) $snapshot->query("SELECT COUNT(*) FROM posts WHERE post_id='reply-001'")->fetchColumn());
        assertSame(false, str_contains(file_get_contents($result['path']), 'ABSENT-SECRET'));
        assertSame($before, hash_file('sha256', $database));
        assertTrue(is_file($root . '/records/post-retractions/post-retraction-target.txt'));
    }

    public function testAbsentRootRetainsRepliesWithContentFreeThread(): void
    {
        [$root, $database] = $this->publicationFixture('root-001');
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
            $record = $root . '/records/post-retractions/post-retraction-' . $target . '.txt';
            rename($record, $record . '.bak');
            $builder = new \ForumRewrite\Offline\PublicOfflineSnapshotBuilder();
            $builder->build($database, $root . '/base.sqlite3');
            rename($record . '.bak', $record);
            $builder->buildUpdate($database, $root . '/update.sqlite3');
            $update = new PDO('sqlite:' . $root . '/update.sqlite3');
            assertSame([$target], $update->query('SELECT post_id FROM excluded_posts')->fetchAll(PDO::FETCH_COLUMN));
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
  if (count(target) !== 0 || count('reply-001') !== 1) throw Error('exclusion or surviving reply failed');
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

    public function testQueuedPublicationRecoversAndProducesBothArtifacts(): void
    {
        [$root, $database] = $this->publicationFixture('reply-20260526032118-654b2b08');
        $other = 'reply-20260526032816-a1447d49';
        $record = $root . '/records/post-retractions/post-retraction-reply-20260526032118-654b2b08.txt';
        file_put_contents($root . '/records/post-retractions/post-retraction-' . $other . '.txt', str_replace('reply-20260526032118-654b2b08', $other, file_get_contents($record)));
        $store = new \ForumRewrite\TaskQueue\SqliteTaskQueueStore(new PDO('sqlite::memory:'));
        $task = $store->enqueue($store::PUBLISH_OFFLINE_SNAPSHOT, 'offline-snapshot');
        $publisher = new \ForumRewrite\Offline\OfflineSnapshotPublisher($root . '/static');
        $publisher->publish($database);
        $before = hash_file('sha256', $publisher->snapshotPath());
        $valid = file_get_contents($record); file_put_contents($record, 'corrupt');
        $worker = new \ForumRewrite\TaskQueue\TaskQueueWorker($store, static function (): void {}, null,
            static function () use ($publisher, $database): void {
                $publisher->publishUpdate($database); $publisher->publish($database);
            });
        assertSame(1, $worker->run()['retried']);
        assertSame($before, hash_file('sha256', $publisher->snapshotPath()));
        assertStringContains('inspect canonical evidence and retry', $store->findById($task['id'])['failure_message']);
        file_put_contents($record, $valid);
        assertSame(1, $worker->run()['completed']);
        assertSame('completed', $store->findById($task['id'])['status']);
        foreach ([$publisher->snapshotPath(), $publisher->updatePath()] as $path) {
            $pdo = new PDO('sqlite:' . $path);
            assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM excluded_posts')->fetchColumn());
            assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE post_id='reply-001'")->fetchColumn());
        }
    }

    public function testOrdinarySnapshotSidecarRemainsCompatibleWithStrictCleanup(): void
    {
        [$root, $database] = $this->publicationFixture();
        unlink($root . '/records/post-retractions/post-retraction-target.txt');
        $publisher = new \ForumRewrite\Offline\OfflineSnapshotPublisher($root . '/static');
        $publisher->publish($database);
        assertSame(PublicationGuard::generation($root), trim(file_get_contents($publisher->snapshotPath() . '.retraction-generation')));
        $sweep = \ForumRewrite\Retraction\ArtifactSweep::run([$root . '/static'], PublicationGuard::generation($root), [], 1000);
        assertTrue(is_file($publisher->snapshotPath()));
        assertTrue(is_file(dirname($publisher->snapshotPath()) . '/manifest.json'));
    }

    public function testPinnedContextPublishesThroughReappearanceButRejectsUnrelatedChanges(): void
    {
        [$root, $database] = $this->publicationFixture();
        $token = PublicationGuard::beginSnapshot($database);
        file_put_contents($root . '/records/posts/target.txt', "Post-ID: target\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: approval\nAuthor-Identity-ID: openpgp:" . str_repeat('b', 40) . "\n\nReappeared\n");
        $published = false;
        PublicationGuard::publish($token, $root . '/candidate', static function () use (&$published): void { $published = true; });
        assertTrue($published);
        $other = str_replace(['post-retraction-target', 'Post-ID: target'], ['post-retraction-other', 'Post-ID: other'], file_get_contents($root . '/records/post-retractions/post-retraction-target.txt'));
        file_put_contents($root . '/records/post-retractions/post-retraction-other.txt', $other);
        $published = false;
        try {
            PublicationGuard::publish($token, $root . '/candidate', static function () use (&$published): void { $published = true; });
            throw new LogicException('Expected unrelated evidence change rejection');
        } catch (RuntimeException $error) {
            assertStringContains('changed during publication', $error->getMessage());
        }
        assertSame(false, $published);
    }

    public function testLaterBuildUsesExistingAuthorAndProtectedPostAuthority(): void
    {
        foreach ([['a', 'general', true], ['b', 'general', false], ['a', 'approval', false]] as [$author, $tags, $effective]) {
            $root = $this->fixture();
            assertSame(['target' => true], ExchangeRetractionState::snapshot($root)['absent']);
            file_put_contents($root . '/records/posts/target.txt', "Post-ID: target\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: $tags\nAuthor-Identity-ID: openpgp:" . str_repeat($author, 40) . "\n\nReappeared\n");
            $next = ExchangeRetractionState::snapshot($root);
            assertSame([], $next['absent']);
            assertSame($effective, isset($next['ids']['target']));
            assertSame($effective, isset(ExchangeRetractionState::read($root)['target']));
        }
    }

    public function testDirectCliAndConfiguredQueuePublishReportedAbsentTargets(): void
    {
        [$root, $database] = $this->publicationFixture('reply-20260526032118-654b2b08');
        $first = $root . '/records/post-retractions/post-retraction-reply-20260526032118-654b2b08.txt';
        file_put_contents($root . '/records/post-retractions/post-retraction-reply-20260526032816-a1447d49.txt',
            str_replace('reply-20260526032118-654b2b08', 'reply-20260526032816-a1447d49', file_get_contents($first)));
        file_put_contents($root . '/secrets.php', '<?php return [];');
        $env = 'FORUM_APPROVED_MEMBERS_ONLY=false FORUM_SECRETS_PATH=' . escapeshellarg($root . '/secrets.php') . ' ';
        $args = ' --repository-root=' . escapeshellarg($root) . ' --database-path=' . escapeshellarg($database) . ' --static-html-root=' . escapeshellarg($root . '/static');
        $base = escapeshellarg(dirname(__DIR__) . '/v3');
        exec($env . $base . ' offline publish' . $args . ' 2>&1', $output, $code);
        assertSame(0, $code, implode("\n", $output));
        assertTrue(is_file($root . '/static/offline/update.sqlite3'));
        $publishedHash = hash_file('sha256', $root . '/static/offline/snapshot.sqlite3');
        exec(str_replace('FORUM_APPROVED_MEMBERS_ONLY=false', 'FORUM_APPROVED_MEMBERS_ONLY=true', $env) . $base . ' offline publish' . $args . ' 2>&1', $privateOutput, $privateCode);
        assertSame(2, $privateCode);
        assertSame($publishedHash, hash_file('sha256', $root . '/static/offline/snapshot.sqlite3'));
        $queue = $root . '/queue.sqlite3';
        $store = new \ForumRewrite\TaskQueue\SqliteTaskQueueStore(new PDO('sqlite:' . $queue));
        $task = $store->enqueue($store::PUBLISH_OFFLINE_SNAPSHOT, 'offline-snapshot');
        exec($env . $base . ' task-queue run' . $args . ' --task-id=' . $task['id'] . ' --queue-database-path=' . escapeshellarg($queue)
            . ' --error-log=' . escapeshellarg($root . '/errors.log') . ' 2>&1', $queueOutput, $code);
        assertSame(0, $code, implode("\n", $queueOutput));
        assertSame('completed', $store->findById($task['id'])['status']);
        foreach (['snapshot', 'update'] as $name) {
            $pdo = new PDO('sqlite:' . $root . '/static/offline/' . $name . '.sqlite3');
            assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM excluded_posts')->fetchColumn());
            assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE post_id LIKE 'reply-20260526%'")->fetchColumn());
        }
    }

    public function testMissingTrackedRetractionStillBlocks(): void
    {
        $root = $this->fixture();
        exec('git -C ' . escapeshellarg($root) . ' init -q');
        exec('git -C ' . escapeshellarg($root) . ' add records');
        exec('git -C ' . escapeshellarg($root) . ' -c user.name=Test -c user.email=test@example.invalid commit -qm fixture');
        unlink($root . '/records/post-retractions/post-retraction-target.txt');
        assertSame(null, ExchangeRetractionState::snapshot($root));
    }
}
