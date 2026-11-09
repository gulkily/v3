<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/RebuildHistoryFixture.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\ReadModel\ReadModelUnavailable;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;

/** Page requests hand rebuilds to the queue when a worker can run them, and never rebuild then. */
final class ReadModelRequestRebuildTest
{
    public function testStaleReadModelIsServedAndOneRebuildIsQueued(): void
    {
        $this->scenario(workerFresh: true, test: function (RebuildHistoryFixture $fixture, string $db, SqliteTaskQueueStore $store): void {
            (new ReadModelStaleMarker($db))->mark(['reason' => 'test']);
            $inode = $this->inode($db);

            $this->get($fixture, $db);
            $this->get($fixture, $db);

            assertSame($inode, $this->inode($db), 'the request did not rebuild');
            assertSame(1, $store->counts()['queued']);
        });
    }

    public function testReadModelBehindHeadIsServedAndQueued(): void
    {
        $this->scenario(workerFresh: true, test: function (RebuildHistoryFixture $fixture, string $db, SqliteTaskQueueStore $store): void {
            $this->commit($fixture, 300);
            $inode = $this->inode($db);

            $this->get($fixture, $db);

            assertSame($inode, $this->inode($db));
            assertSame(1, $store->counts()['queued']);
        });
    }

    public function testRequestWaitsForAWriteInProgressInsteadOfQueueing(): void
    {
        $this->scenario(workerFresh: true, test: function (RebuildHistoryFixture $fixture, string $db, SqliteTaskQueueStore $store): void {
            $this->commit($fixture, 301);
            $head = $fixture->git('rev-parse', 'HEAD');
            $ready = $fixture->root . '/writer-ready';
            // A writer that holds the lock briefly and brings the read model up to HEAD, like a commit's incremental update.
            $script = '$l=fopen($argv[1],"c+");flock($l,LOCK_EX);touch($argv[3]);usleep(800000);'
                . '(new PDO("sqlite:".$argv[2]))->exec("UPDATE metadata SET value=\'".$argv[4]."\' WHERE key=\'repository_head\'");flock($l,LOCK_UN);';
            $writer = proc_open([PHP_BINARY, '-r', $script, dirname($db) . '/forum-rewrite.lock', $db, $ready, $head], [], $pipes);
            try {
                for ($i = 0; $i < 200 && !is_file($ready); $i++) usleep(10000);
                assertTrue(is_file($ready), 'writer took the lock');
                $this->get($fixture, $db);
            } finally {
                proc_close($writer);
            }

            assertSame(0, $store->counts()['queued']);
        });
    }

    public function testOldSchemaShowsUpdatePageAndQueuesOnce(): void
    {
        $this->scenario(workerFresh: true, test: function (RebuildHistoryFixture $fixture, string $db, SqliteTaskQueueStore $store): void {
            (new PDO('sqlite:' . $db))->exec("UPDATE metadata SET value = '1' WHERE key = 'schema_version'");
            $inode = $this->inode($db);

            foreach ([1, 2] as $attempt) {
                try {
                    $this->get($fixture, $db);
                    throw new RuntimeException('Expected the read model to be unavailable.');
                } catch (ReadModelUnavailable $unavailable) {
                    assertSame('updating', $unavailable->state);
                }
            }

            assertSame($inode, $this->inode($db));
            assertSame(1, $store->counts()['queued']);
        });
    }

    public function testWithoutAWorkerTheRequestStillRebuildsBySwapping(): void
    {
        $this->scenario(workerFresh: false, test: function (RebuildHistoryFixture $fixture, string $db, SqliteTaskQueueStore $store): void {
            (new ReadModelStaleMarker($db))->mark(['reason' => 'test']);
            $inode = $this->inode($db);

            $this->get($fixture, $db);

            assertTrue($this->inode($db) !== $inode, 'the request built a new file and swapped it in');
            assertSame(false, (new ReadModelStaleMarker($db))->exists());
            assertSame(0, $store->counts()['queued']);
        });
    }

    private function scenario(bool $workerFresh, callable $test): void
    {
        $fixture = new RebuildHistoryFixture(labels: 1);
        $db = $fixture->root . '/state/read.sqlite3';
        mkdir(dirname($db), 0777, true);
        $queuePath = $fixture->root . '/state/queue.sqlite3';
        $previousQueuePath = getenv('FORUM_TASK_QUEUE_DATABASE_PATH');
        $previousLaunch = getenv('FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED');
        putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . $queuePath);
        putenv('FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED');
        try {
            (new ReadModelBuilder($fixture->repository, $db, new CanonicalRecordRepository($fixture->repository)))->rebuild();
            $store = new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath));
            if ($workerFresh) {
                $run = $store->startExecutorRun();
                $store->completeExecutorRun($run['id'], ['recovered' => 0, 'claimed' => 0, 'completed' => 0, 'continued' => 0, 'retried' => 0, 'failed' => 0], []);
            }
            $test($fixture, $db, $store);
        } finally {
            $previousQueuePath === false ? putenv('FORUM_TASK_QUEUE_DATABASE_PATH') : putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . $previousQueuePath);
            $previousLaunch === false ? putenv('FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED') : putenv('FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED=' . $previousLaunch);
            $fixture->remove();
        }
    }

    private function get(RebuildHistoryFixture $fixture, string $db): void
    {
        ob_start();
        try {
            (new Application(dirname(__DIR__), $fixture->repository, $db))->handle('GET', '/activity/');
        } finally {
            ob_end_clean();
        }
    }

    private function commit(RebuildHistoryFixture $fixture, int $i): void
    {
        $fixture->write('thread-labels', $i);
        $fixture->git('add', '.');
        $fixture->git('commit', '-qm', 'outside write ' . $i);
    }

    private function inode(string $path): int
    {
        clearstatcache(true, $path);
        return (int) fileinode($path);
    }
}
