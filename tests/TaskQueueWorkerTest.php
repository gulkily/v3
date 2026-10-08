<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\TaskQueue\TaskQueueWorker;

final class TaskQueueWorkerTest
{
    public function testWorkerCompletesClaimedReadModelRebuildTask(): void
    {
        $store = $this->store();
        $task = $store->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model');
        $runs = 0;
        $worker = new TaskQueueWorker($store, static function () use (&$runs): void {
            $runs++;
        });

        $summary = $worker->run();
        $stored = $store->findById($task['id']);

        assertSame(1, $runs);
        assertSame(1, $summary['claimed']);
        assertSame(1, $summary['completed']);
        assertSame('completed', $stored['status']);
    }

    public function testWorkerPassesTheClaimedTaskIdToTheRebuildHandler(): void
    {
        $store = $this->store();
        $task = $store->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model');
        $receivedTaskId = null;
        $worker = new TaskQueueWorker($store, static function (int $taskId) use (&$receivedTaskId): void {
            $receivedTaskId = $taskId;
        });

        $worker->run();

        assertSame($task['id'], $receivedTaskId);
    }

    public function testWorkerReportsTaskLifecycle(): void
    {
        $store = $this->store();
        $store->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model');
        $events = [];
        $worker = new TaskQueueWorker($store, static function (): void {
        });

        $worker->run(1, static function (string $event, array $task) use (&$events): void {
            $events[] = [$event, $task['status'] ?? null];
        });

        assertSame([['started', 'running'], ['finished', 'completed']], $events);
    }

    public function testWorkerRetriesThenFailsBoundedRebuildFailure(): void
    {
        $store = $this->store();
        $task = $store->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model', 2);
        $worker = new TaskQueueWorker($store, static function (): void {
            throw new RuntimeException('Rebuild is unavailable.');
        });

        $first = $worker->run();
        $second = $worker->run();
        $stored = $store->findById($task['id']);

        assertSame(1, $first['retried']);
        assertSame(1, $second['failed']);
        assertSame('failed', $stored['status']);
        assertSame(2, $stored['attempts']);
        assertSame('read_model_rebuild_failed', $stored['failure_code']);
    }

    public function testWorkerRefusesUnknownQueuedTaskWithoutRunningHandler(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new SqliteTaskQueueStore($pdo);
        $pdo->exec(
            "INSERT INTO internal_tasks (type, deduplication_key, status, attempts, max_attempts, requested_at)
             VALUES ('unknown', 'unknown-task', 'queued', 0, 1, '2026-01-01T00:00:00+00:00')"
        );
        $runs = 0;
        $worker = new TaskQueueWorker($store, static function () use (&$runs): void {
            $runs++;
        });

        $summary = $worker->run();
        $stored = $store->recent(1)[0];

        assertSame(0, $runs);
        assertSame(1, $summary['failed']);
        assertSame('failed', $stored['status']);
        assertSame('unsupported_task_type', $stored['failure_code']);
    }

    public function testWorkerContinuesBoundedFastScoreSweepWithoutUsingFailureRetries(): void
    {
        $store = $this->store();
        $task = $store->enqueue(SqliteTaskQueueStore::FAST_SCORE_SWEEP, 'fast-score', 2);
        $runs = 0;
        $worker = new TaskQueueWorker(
            $store,
            static function (): void {
            },
            static function () use (&$runs): array {
                $runs++;
                return ['remaining' => $runs === 1, 'processed' => 1];
            },
        );

        $first = $worker->run();
        $second = $worker->run();
        $stored = $store->findById($task['id']);

        assertSame(1, $first['continued']);
        assertSame(0, $first['retried']);
        assertSame(1, $second['completed']);
        assertSame(2, $runs);
        assertSame('completed', $stored['status']);
        assertSame(1, $stored['attempts']);
    }

    public function testWorkerCompletesOfflineSnapshotPublicationTask(): void
    {
        $store = $this->store();
        $task = $store->enqueue(SqliteTaskQueueStore::PUBLISH_OFFLINE_SNAPSHOT, 'offline-snapshot');
        $runs = 0;
        $worker = new TaskQueueWorker(
            $store,
            static function (): void {
            },
            null,
            static function () use (&$runs): void {
                $runs++;
            },
        );

        $summary = $worker->run();
        $stored = $store->findById($task['id']);

        assertSame(1, $runs);
        assertSame(1, $summary['completed']);
        assertSame('completed', $stored['status']);
    }

    public function testWorkerRetriesOfflineSnapshotPublicationFailure(): void
    {
        $store = $this->store();
        $task = $store->enqueue(SqliteTaskQueueStore::PUBLISH_OFFLINE_SNAPSHOT, 'offline-snapshot', 2);
        $worker = new TaskQueueWorker(
            $store,
            static function (): void {
            },
            null,
            static function (): void {
                throw new RuntimeException('Snapshot storage is unavailable.');
            },
        );

        $first = $worker->run();
        $second = $worker->run();
        $stored = $store->findById($task['id']);

        assertSame(1, $first['retried']);
        assertSame(1, $second['failed']);
        assertSame('offline_snapshot_publication_failed', $stored['failure_code']);
    }

    public function testWorkerCompletesAgentReplyTaskThroughConfiguredHandler(): void
    {
        $store = $this->store();
        $task = $store->enqueue(SqliteTaskQueueStore::AGENT_REPLY, 'post-001@content-001');
        $received = null;
        $worker = new TaskQueueWorker(
            $store,
            static function (): void {
            },
            null,
            null,
            static function (array $claimedTask) use (&$received): void {
                $received = $claimedTask['deduplication_key'];
            },
        );

        $summary = $worker->run();

        assertSame('post-001@content-001', $received);
        assertSame(1, $summary['completed']);
        assertSame('completed', $store->findById($task['id'])['status']);
    }

    public function testWorkerDoesNotRetryInvalidAgentReplyTask(): void
    {
        $store = $this->store();
        $task = $store->enqueue(SqliteTaskQueueStore::AGENT_REPLY, 'malformed');
        $worker = new TaskQueueWorker(
            $store,
            static function (): void {
            },
            null,
            null,
            static function (): void {
                throw new InvalidArgumentException('Invalid task.');
            },
        );

        $summary = $worker->run();

        assertSame(1, $summary['failed']);
        assertSame('agent_reply_task_invalid', $store->findById($task['id'])['failure_code']);
    }

    private function store(): SqliteTaskQueueStore
    {
        return new SqliteTaskQueueStore(new PDO('sqlite::memory:'));
    }
}
