<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Support\OperatorStatusCollector;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;

final class OperatorStatusCollectorTest
{
    public function testCollectReportsReadyReadModelAndDoesNotInitializeRuntimeState(): void
    {
        [$databasePath, $queuePath, $lockPath] = $this->paths();
        try {
            $status = $this->collector($databasePath, $queuePath, $lockPath)->collect();

            assertSame('ready', $status['read_model']['status']);
            assertSame('ready', $status['read_model']['freshness_status']);
            assertSame('unlocked', $status['read_model']['lock_status']);
            assertSame('not_initialized', $status['task_queue']['status']);
            assertSame('absent', $status['task_queue']['rebuild_task_status']);
            assertSame('not_observed', $status['task_queue']['automatic_recovery_status']);
            assertSame(false, is_file($queuePath));
            assertSame(false, is_file($lockPath));
        } finally {
            $this->clean($databasePath, $queuePath, $lockPath);
        }
    }

    public function testCollectReportsStaleUnavailableAndLockedReadModelStates(): void
    {
        [$databasePath, $queuePath, $lockPath] = $this->paths();
        try {
            $collector = $this->collector($databasePath, $queuePath, $lockPath);
            (new ReadModelStaleMarker($databasePath))->mark(['reason' => 'test', 'commit_sha' => 'abc']);
            $stale = $collector->collect();
            assertSame('stale', $stale['read_model']['status']);
            assertSame('present', $stale['read_model']['stale_marker']);

            $locked = (new ExecutionLock($lockPath, 0))->withExclusiveLock($collector->collect(...));
            assertSame('locked', $locked['read_model']['lock_status']);

            @unlink($databasePath);
            $unavailable = $collector->collect();
            assertSame('unavailable', $unavailable['read_model']['status']);
        } finally {
            $this->clean($databasePath, $queuePath, $lockPath);
        }
    }

    public function testCollectMarksMissingCommitCapabilityAsStale(): void
    {
        [$databasePath, $queuePath, $lockPath] = $this->paths();
        try {
            (new \PDO('sqlite:' . $databasePath))->exec('DROP TABLE commits');
            $status = $this->collector($databasePath, $queuePath, $lockPath)->collect();

            assertSame('stale', $status['read_model']['status']);
            assertSame('ready', $status['read_model']['freshness_status']);
            assertSame('unavailable', $status['read_model']['commits_capability']);
        } finally {
            $this->clean($databasePath, $queuePath, $lockPath);
        }
    }

    public function testCollectReportsQueuedRunningAndFailedRebuildTasks(): void
    {
        [$databasePath, $queuePath, $lockPath] = $this->paths();
        try {
            $store = new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath));
            $task = $store->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model', 1);
            $collector = $this->collector($databasePath, $queuePath, $lockPath);

            assertSame('queued', $collector->collect()['task_queue']['rebuild_task_status']);
            $claimed = $store->claimNext()[0];
            assertSame($task['id'], $claimed['id']);
            assertSame('running', $collector->collect()['task_queue']['rebuild_task_status']);

            $store->markFailed($claimed['id'], 'test_failure', 'Test failure.', false);
            assertSame('failed', $collector->collect()['task_queue']['rebuild_task_status']);
        } finally {
            $this->clean($databasePath, $queuePath, $lockPath);
        }
    }

    public function testCollectReportsFreshExecutorHeartbeat(): void
    {
        [$databasePath, $queuePath, $lockPath] = $this->paths();
        try {
            $store = new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath));
            $run = $store->startExecutorRun();
            $store->completeExecutorRun($run['id'], [
                'recovered' => 0,
                'claimed' => 0,
                'completed' => 0,
                'continued' => 0,
                'retried' => 0,
                'failed' => 0,
            ], []);

            $queue = $this->collector($databasePath, $queuePath, $lockPath)->collect()['task_queue'];

            assertSame('fresh', $queue['executor_status']);
            assertTrue($queue['executor_last_completed_at'] !== 'none');
        } finally {
            $this->clean($databasePath, $queuePath, $lockPath);
        }
    }

    public function testCollectReportsAutomaticRecoveryAndDetachedLaunchStatus(): void
    {
        [$databasePath, $queuePath, $lockPath] = $this->paths();
        try {
            $store = new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath));
            $request = $store->requestAutomaticRebuild(SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON);
            $launchId = $store->reserveAutomaticRecoveryLaunch(SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON, $request['task']['id']);
            $store->completeAutomaticRecoveryLaunch($launchId, 'launched');

            $queue = $this->collector($databasePath, $queuePath, $lockPath)->collect()['task_queue'];

            assertSame('pending', $queue['automatic_recovery_status']);
            assertSame('launched', $queue['automatic_recovery_launch_status']);
        } finally {
            $this->clean($databasePath, $queuePath, $lockPath);
        }
    }

    /**
     * @return array{string,string,string}
     */
    private function paths(): array
    {
        $directory = sys_get_temp_dir() . '/forum-operator-status-' . bin2hex(random_bytes(6));
        if (!mkdir($directory, 0700) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create operator-status test directory.');
        }

        $databasePath = $directory . '/read.sqlite3';
        $repositoryRoot = __DIR__ . '/fixtures/parity_minimal_v1';
        (new ReadModelBuilder($repositoryRoot, $databasePath, new CanonicalRecordRepository($repositoryRoot)))->rebuild();

        return [$databasePath, $directory . '/queue.sqlite3', $directory . '/forum-rewrite.lock'];
    }

    private function collector(string $databasePath, string $queuePath, string $lockPath): OperatorStatusCollector
    {
        $repositoryRoot = __DIR__ . '/fixtures/parity_minimal_v1';

        return new OperatorStatusCollector(
            $repositoryRoot,
            $databasePath,
            $queuePath,
            new ExecutionLock($lockPath, 0),
            new ReadModelStaleMarker($databasePath),
            static fn (): PDO => (new ReadModelConnection($databasePath))->open(),
        );
    }

    private function clean(string $databasePath, string $queuePath, string $lockPath): void
    {
        foreach ([$databasePath, $databasePath . '-journal', $queuePath, $queuePath . '-journal', $lockPath, dirname($databasePath) . '/read_model_stale.json'] as $path) {
            @unlink($path);
        }
        @rmdir(dirname($databasePath));
    }
}
