<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\TaskQueue\SqliteTaskQueueStore;

final class TaskQueueStoreTest
{
    public function testEnqueueDeduplicatesOutstandingTask(): void
    {
        $store = $this->store();
        $first = $store->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model');
        $second = $store->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model');

        assertSame(true, $first['enqueued']);
        assertSame(false, $second['enqueued']);
        assertSame($first['id'], $second['id']);
    }

    public function testEnqueueDeduplicatesOutstandingFastScoreSweep(): void
    {
        $store = $this->store();
        $first = $store->enqueue(SqliteTaskQueueStore::FAST_SCORE_SWEEP, 'fast-score');
        $second = $store->enqueue(SqliteTaskQueueStore::FAST_SCORE_SWEEP, 'fast-score');

        assertSame(true, $first['enqueued']);
        assertSame(false, $second['enqueued']);
        assertSame($first['id'], $second['id']);
    }

    public function testEnqueueDeduplicatesOutstandingOfflineSnapshotPublication(): void
    {
        $store = $this->store();
        $first = $store->enqueue(SqliteTaskQueueStore::PUBLISH_OFFLINE_SNAPSHOT, 'offline-snapshot');
        $second = $store->enqueue(SqliteTaskQueueStore::PUBLISH_OFFLINE_SNAPSHOT, 'offline-snapshot');

        assertSame(true, $first['enqueued']);
        assertSame(false, $second['enqueued']);
        assertSame($first['id'], $second['id']);
    }

    public function testRequeueClaimedDoesNotSpendAWorkerFailureAttempt(): void
    {
        $store = $this->store();
        $store->enqueue(SqliteTaskQueueStore::FAST_SCORE_SWEEP, 'fast-score');
        $claimed = $store->claimNext()[0];

        $requeued = $store->requeueClaimed($claimed['id']);

        assertSame('queued', $requeued['status']);
        assertSame(0, $requeued['attempts']);
    }

    public function testClaimIsAtomicAndIncrementsAttemptCount(): void
    {
        $store = $this->store();
        $store->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model');

        $first = $store->claimNext();
        $second = $store->claimNext();

        assertSame(1, count($first));
        assertSame('running', $first[0]['status']);
        assertSame(1, $first[0]['attempts']);
        assertSame(true, $first[0]['claimed']);
        assertSame([], $second);
    }

    public function testRetryableFailureReturnsTaskToQueueUntilAttemptLimit(): void
    {
        $store = $this->store();
        $task = $store->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model', 2);

        $claimed = $store->claimNext()[0];
        $retry = $store->markFailed($claimed['id'], 'temporary', 'Try again.', true);
        $lastClaim = $store->claimNext()[0];
        $failed = $store->markFailed($lastClaim['id'], 'temporary', 'Still unavailable.', true);

        assertSame('queued', $retry['status']);
        assertSame(1, $retry['attempts']);
        assertSame('failed', $failed['status']);
        assertSame(2, $failed['attempts']);
        assertSame($task['id'], $failed['id']);
    }

    public function testRecoverAbandonedRunningTaskRequeuesIt(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new SqliteTaskQueueStore($pdo);
        $store->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model');
        $claimed = $store->claimNext()[0];
        $pdo->exec("UPDATE internal_tasks SET claimed_at = '2000-01-01T00:00:00+00:00' WHERE id = " . $claimed['id']);
        assertSame(1, $claimed['attempts']);

        $recovered = $store->recoverAbandonedRunning();
        $task = $store->findById($claimed['id']);

        assertSame(1, $recovered);
        assertSame('queued', $task['status']);
        assertSame('worker_interrupted', $task['failure_code']);
    }

    public function testRejectsUnknownTaskType(): void
    {
        $store = $this->store();

        try {
            $store->enqueue('shell_command', 'anything');
        } catch (InvalidArgumentException $exception) {
            assertSame('Unsupported internal task type: shell_command', $exception->getMessage());
            return;
        }

        throw new RuntimeException('Expected unsupported task type to be rejected.');
    }

    public function testExecutorRunHistoryCapturesSafeTaskOutcomes(): void
    {
        $store = $this->store();
        $run = $store->startExecutorRun();
        $completed = $store->completeExecutorRun($run['id'], [
            'recovered' => 0,
            'claimed' => 1,
            'completed' => 1,
            'continued' => 0,
            'retried' => 0,
            'failed' => 0,
        ], [['id' => 9, 'status' => 'completed', 'failure_code' => null]]);

        assertSame('completed', $completed['status']);
        assertTrue($completed['completed_at'] !== null);
        assertSame(1, $completed['summary']['claimed']);
        assertSame([['id' => 9, 'status' => 'completed', 'failure_code' => null]], $completed['task_outcomes']);
        assertSame($run['id'], $store->recentExecutorRuns(1)[0]['id']);
    }

    public function testExecutorHeartbeatDistinguishesFreshAndStaleCompletedRuns(): void
    {
        $store = $this->store();
        $run = $store->startExecutorRun();
        $completed = $store->completeExecutorRun($run['id'], [
            'recovered' => 0,
            'claimed' => 0,
            'completed' => 0,
            'continued' => 0,
            'retried' => 0,
            'failed' => 0,
        ], []);

        assertSame('fresh', $store->executorHeartbeatStatus(120, strtotime($completed['completed_at']))['status']);
        assertSame('stale', $store->executorHeartbeatStatus(120, strtotime($completed['completed_at']) + 121)['status']);
    }

    public function testAutomaticRebuildCircuitBlocksAfterTerminalFailureUntilReset(): void
    {
        $store = $this->store();
        $first = $store->requestAutomaticRebuild(SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON, 1);
        $same = $store->requestAutomaticRebuild(SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON, 1);
        $claimed = $store->claimNext()[0];
        $store->markFailed($claimed['id'], 'test_failure', 'Test failure.', true);

        assertSame('queued', $first['status']);
        assertSame('already_outstanding', $same['status']);
        assertSame($first['task']['id'], $same['task']['id']);
        assertSame('blocked', $store->automaticRecoveryStatus(SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON)['status']);
        assertSame('blocked', $store->requestAutomaticRebuild(SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON)['status']);
        assertSame(true, $store->resetAutomaticRecovery(SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON));
        assertSame('queued', $store->requestAutomaticRebuild(SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON)['status']);
    }

    public function testAutomaticRebuildCircuitOpensForAnAbandonedFinalAttempt(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new SqliteTaskQueueStore($pdo);
        $request = $store->requestAutomaticRebuild(SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON, 1);
        $claimed = $store->claimNext()[0];
        $pdo->exec("UPDATE internal_tasks SET claimed_at = '2000-01-01T00:00:00+00:00' WHERE id = " . $claimed['id']);

        assertSame(1, $store->recoverAbandonedRunning());
        assertSame($request['task']['id'], $claimed['id']);
        assertSame('blocked', $store->automaticRecoveryStatus(SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON)['status']);
    }

    public function testAutomaticRecoveryReservesOnlyOneDetachedLaunchPerTask(): void
    {
        $store = $this->store();
        $request = $store->requestAutomaticRebuild(SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON);
        $taskId = $request['task']['id'];

        $launchId = $store->reserveAutomaticRecoveryLaunch(SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON, $taskId);

        assertTrue($launchId !== null);
        assertSame(null, $store->reserveAutomaticRecoveryLaunch(SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON, $taskId));
        assertSame('starting', $store->automaticRecoveryLaunchStatus(SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON, $taskId));

        $store->completeAutomaticRecoveryLaunch($launchId, 'launched');

        assertSame('launched', $store->automaticRecoveryLaunchStatus(SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON, $taskId));
    }

    public function testPruneHistoryRetainsActiveTaskAndBoundsTerminalRecords(): void
    {
        $store = $this->store();
        $first = $store->enqueue(SqliteTaskQueueStore::FAST_SCORE_SWEEP, 'first');
        $store->markCompleted($store->claimNext()[0]['id']);
        $second = $store->enqueue(SqliteTaskQueueStore::FAST_SCORE_SWEEP, 'second');
        $store->markCompleted($store->claimNext()[0]['id']);
        $active = $store->enqueue(SqliteTaskQueueStore::FAST_SCORE_SWEEP, 'active');
        $store->recordTaskProgress($first['id'], 'first checkpoint');
        $store->recordTaskProgress($second['id'], 'second checkpoint');
        $firstRun = $store->startExecutorRun();
        $store->completeExecutorRun($firstRun['id'], $this->emptyRunSummary(), []);
        $secondRun = $store->startExecutorRun();
        $store->completeExecutorRun($secondRun['id'], $this->emptyRunSummary(), []);

        $pruned = $store->pruneHistory(1, 1, 1);

        assertSame(1, $pruned['tasks']);
        assertSame(1, $pruned['executor_runs']);
        assertSame(1, $pruned['progress_events']);
        assertSame(null, $store->findById($first['id']));
        assertSame('completed', $store->findById($second['id'])['status']);
        assertSame('queued', $store->findById($active['id'])['status']);
        assertSame(1, count($store->recentExecutorRuns(10)));
        assertSame('second checkpoint', $store->recentTaskProgress(10)[0]['message']);
    }

    /** @return array{recovered:int,claimed:int,completed:int,continued:int,retried:int,failed:int} */
    private function emptyRunSummary(): array
    {
        return ['recovered' => 0, 'claimed' => 0, 'completed' => 0, 'continued' => 0, 'retried' => 0, 'failed' => 0];
    }

    private function store(): SqliteTaskQueueStore
    {
        return new SqliteTaskQueueStore(new PDO('sqlite::memory:'));
    }
}
