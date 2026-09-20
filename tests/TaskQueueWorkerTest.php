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

    private function store(): SqliteTaskQueueStore
    {
        return new SqliteTaskQueueStore(new PDO('sqlite::memory:'));
    }
}
