<?php

declare(strict_types=1);

namespace ForumRewrite\TaskQueue;

use InvalidArgumentException;
use PDO;

final class SqliteTaskQueueStore
{
    public const REBUILD_READ_MODEL = 'rebuild_read_model';
    public const FAST_SCORE_SWEEP = 'fast_score_sweep';
    public const PUBLISH_OFFLINE_SNAPSHOT = 'publish_offline_snapshot';
    public const AGENT_REPLY = 'agent_reply';
    public const EXECUTOR_HEARTBEAT_FRESHNESS_SECONDS = 120;
    public const READ_MODEL_SCHEMA_RECOVERY_REASON = 'read_model_schema';
    public const TERMINAL_TASK_HISTORY_LIMIT = 100;
    public const EXECUTOR_RUN_HISTORY_LIMIT = 100;
    public const TASK_PROGRESS_HISTORY_LIMIT = 250;

    public function __construct(
        private readonly PDO $pdo,
        bool $ensureSchema = true,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        if ($ensureSchema) {
            $this->ensureSchema();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function enqueue(string $type, string $deduplicationKey, int $maxAttempts = 3): array
    {
        $this->assertAllowedType($type);
        $deduplicationKey = trim($deduplicationKey);
        if ($deduplicationKey === '') {
            throw new InvalidArgumentException('Task deduplication key is required.');
        }

        $maxAttempts = max(1, $maxAttempts);

        return $this->withImmediateTransaction(
            fn (): array => $this->enqueueWithinTransaction($type, $deduplicationKey, $maxAttempts)
        );
    }

    /**
     * @return array{status:string,task:?array<string, mixed>}
     */
    public function requestAutomaticRebuild(string $reason, int $maxAttempts = 3): array
    {
        $this->assertRecoveryReason($reason);

        return $this->withImmediateTransaction(function () use ($reason, $maxAttempts): array {
            $state = $this->recoveryStateWithinTransaction($reason);
            if ($state['status'] === 'blocked') {
                return ['status' => 'blocked', 'task' => null];
            }

            $task = $this->enqueueWithinTransaction(self::REBUILD_READ_MODEL, 'read-model', $maxAttempts);
            $now = gmdate('c');
            $upsert = $this->pdo->prepare(
                'INSERT INTO automatic_recovery_state (reason, status, task_id, updated_at, opened_at)
                 VALUES (:reason, :status, :task_id, :updated_at, NULL)
                 ON CONFLICT(reason) DO UPDATE SET
                    status = excluded.status,
                    task_id = excluded.task_id,
                    updated_at = excluded.updated_at,
                    opened_at = NULL'
            );
            $upsert->execute([
                'reason' => $reason,
                'status' => 'pending',
                'task_id' => $task['id'],
                'updated_at' => $now,
            ]);

            return ['status' => $task['enqueued'] ? 'queued' : 'already_outstanding', 'task' => $task];
        });
    }

    public function resetAutomaticRecovery(string $reason): bool
    {
        $this->assertRecoveryReason($reason);

        return $this->withImmediateTransaction(function () use ($reason): bool {
            $stmt = $this->pdo->prepare(
                'UPDATE automatic_recovery_state
                 SET status = :status, task_id = NULL, updated_at = :updated_at, opened_at = NULL
                 WHERE reason = :reason AND status = :blocked'
            );
            $stmt->execute([
                'status' => 'idle',
                'updated_at' => gmdate('c'),
                'reason' => $reason,
                'blocked' => 'blocked',
            ]);

            return $stmt->rowCount() === 1;
        });
    }

    /**
     * @return array{status:string,task_id:?int}
     */
    public function automaticRecoveryStatus(string $reason): array
    {
        $this->assertRecoveryReason($reason);
        $state = $this->recoveryStateWithinTransaction($reason);

        return ['status' => $state['status'], 'task_id' => $state['task_id']];
    }

    /**
     * Reserves the one detached-worker launch allowed for this recovery task.
     *
     * @return int|null The launch record id, or null when another request already reserved it.
     */
    public function reserveAutomaticRecoveryLaunch(string $reason, int $taskId): ?int
    {
        $this->assertRecoveryReason($reason);

        return $this->withImmediateTransaction(function () use ($reason, $taskId): ?int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO automatic_recovery_launches (reason, task_id, status, created_at, completed_at)
                 VALUES (:reason, :task_id, :status, :created_at, NULL)
                 ON CONFLICT(reason, task_id) DO NOTHING'
            );
            $stmt->execute([
                'reason' => $reason,
                'task_id' => $taskId,
                'status' => 'starting',
                'created_at' => gmdate('c'),
            ]);

            return $stmt->rowCount() === 1 ? (int) $this->pdo->lastInsertId() : null;
        });
    }

    public function completeAutomaticRecoveryLaunch(int $launchId, string $status): void
    {
        if (!in_array($status, ['launched', 'failed', 'disabled', 'unavailable'], true)) {
            throw new InvalidArgumentException('Invalid automatic recovery launch status.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE automatic_recovery_launches
             SET status = :status, completed_at = :completed_at
             WHERE id = :id AND status = :starting'
        );
        $stmt->execute([
            'id' => $launchId,
            'status' => $status,
            'starting' => 'starting',
            'completed_at' => gmdate('c'),
        ]);
    }

    public function automaticRecoveryLaunchStatus(string $reason, int $taskId): ?string
    {
        $this->assertRecoveryReason($reason);
        $stmt = $this->pdo->prepare(
            'SELECT status FROM automatic_recovery_launches
             WHERE reason = :reason AND task_id = :task_id'
        );
        $stmt->execute(['reason' => $reason, 'task_id' => $taskId]);
        $status = $stmt->fetchColumn();

        return is_string($status) ? $status : null;
    }

    public function recordTaskProgress(int $taskId, string $message): void
    {
        if ($taskId < 1) {
            throw new InvalidArgumentException('Task id is required for progress.');
        }

        $message = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message) ?? '');
        if ($message === '') {
            throw new InvalidArgumentException('Task progress message is required.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO task_queue_progress_events (task_id, message, created_at)
             VALUES (:task_id, :message, :created_at)'
        );
        $stmt->execute([
            'task_id' => $taskId,
            'message' => substr($message, 0, 300),
            'created_at' => gmdate('c'),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function latestTaskProgress(int $taskId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, task_id, message, created_at
             FROM task_queue_progress_events
             WHERE task_id = :task_id
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute(['task_id' => $taskId]);
        $event = $stmt->fetch();

        return $event === false ? null : $event;
    }

    /** @return list<array<string, mixed>> */
    public function recentTaskProgress(int $limit = 25): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, task_id, message, created_at
             FROM task_queue_progress_events
             ORDER BY id DESC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Retains only bounded terminal history. Active tasks and recovery state are never deleted.
     *
     * @return array{tasks:int,executor_runs:int,progress_events:int,launches:int}
     */
    public function pruneHistory(
        int $terminalTaskLimit = self::TERMINAL_TASK_HISTORY_LIMIT,
        int $executorRunLimit = self::EXECUTOR_RUN_HISTORY_LIMIT,
        int $progressLimit = self::TASK_PROGRESS_HISTORY_LIMIT,
    ): array {
        $terminalTaskLimit = max(1, $terminalTaskLimit);
        $executorRunLimit = max(1, $executorRunLimit);
        $progressLimit = max(1, $progressLimit);

        return $this->withImmediateTransaction(function () use ($terminalTaskLimit, $executorRunLimit, $progressLimit): array {
            $deleteTerminalTasks = $this->pdo->prepare(
                'DELETE FROM internal_tasks
                 WHERE status IN (:completed, :failed)
                   AND id NOT IN (
                       SELECT id FROM internal_tasks
                       WHERE status IN (:completed_recent, :failed_recent)
                       ORDER BY id DESC
                       LIMIT :limit
                   )'
            );
            $deleteTerminalTasks->bindValue('completed', 'completed');
            $deleteTerminalTasks->bindValue('failed', 'failed');
            $deleteTerminalTasks->bindValue('completed_recent', 'completed');
            $deleteTerminalTasks->bindValue('failed_recent', 'failed');
            $deleteTerminalTasks->bindValue('limit', $terminalTaskLimit, PDO::PARAM_INT);
            $deleteTerminalTasks->execute();

            $deleteExecutorRuns = $this->pdo->prepare(
                'DELETE FROM task_queue_executor_runs
                 WHERE completed_at IS NOT NULL
                   AND id NOT IN (
                       SELECT id FROM task_queue_executor_runs
                       WHERE completed_at IS NOT NULL
                       ORDER BY id DESC
                       LIMIT :limit
                   )'
            );
            $deleteExecutorRuns->bindValue('limit', $executorRunLimit, PDO::PARAM_INT);
            $deleteExecutorRuns->execute();

            $deleteProgress = $this->pdo->prepare(
                'DELETE FROM task_queue_progress_events
                 WHERE id NOT IN (
                     SELECT id FROM task_queue_progress_events
                     ORDER BY id DESC
                     LIMIT :limit
                 )'
            );
            $deleteProgress->bindValue('limit', $progressLimit, PDO::PARAM_INT);
            $deleteProgress->execute();

            $deleteLaunches = $this->pdo->prepare(
                'DELETE FROM automatic_recovery_launches
                 WHERE status != :starting
                   AND id NOT IN (
                     SELECT id FROM automatic_recovery_launches
                     WHERE status != :starting_recent
                     ORDER BY id DESC
                     LIMIT :limit
                 )'
            );
            $deleteLaunches->bindValue('starting', 'starting');
            $deleteLaunches->bindValue('starting_recent', 'starting');
            $deleteLaunches->bindValue('limit', $terminalTaskLimit, PDO::PARAM_INT);
            $deleteLaunches->execute();

            return [
                'tasks' => $deleteTerminalTasks->rowCount(),
                'executor_runs' => $deleteExecutorRuns->rowCount(),
                'progress_events' => $deleteProgress->rowCount(),
                'launches' => $deleteLaunches->rowCount(),
            ];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function claimNext(int $limit = 1): array
    {
        $limit = max(1, $limit);

        return $this->withImmediateTransaction(function () use ($limit): array {
            $select = $this->pdo->prepare(
                'SELECT id
                 FROM internal_tasks
                 WHERE status = :status AND attempts < max_attempts
                 ORDER BY requested_at ASC, id ASC
                 LIMIT :limit'
            );
            $select->bindValue('status', 'queued');
            $select->bindValue('limit', $limit, PDO::PARAM_INT);
            $select->execute();

            $claimed = [];
            foreach ($select->fetchAll() as $row) {
                $update = $this->pdo->prepare(
                    'UPDATE internal_tasks
                     SET status = :running, attempts = attempts + 1, claimed_at = :claimed_at
                     WHERE id = :id AND status = :queued AND attempts < max_attempts'
                );
                $update->execute([
                    'running' => 'running',
                    'claimed_at' => gmdate('c'),
                    'id' => (int) $row['id'],
                    'queued' => 'queued',
                ]);
                if ($update->rowCount() !== 1) {
                    continue;
                }

                $task = $this->findById((int) $row['id']);
                if ($task !== null) {
                    $task['claimed'] = true;
                    $claimed[] = $task;
                }
            }

            return $claimed;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, type, deduplication_key, status, attempts, max_attempts, requested_at, claimed_at,
                    completed_at, failure_code, failure_message
             FROM internal_tasks
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 25): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, type, deduplication_key, status, attempts, max_attempts, requested_at, claimed_at,
                    completed_at, failure_code, failure_message
             FROM internal_tasks
             ORDER BY id DESC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn (array $row): array => $this->hydrate($row), $stmt->fetchAll());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestByType(string $type): ?array
    {
        $this->assertAllowedType($type);

        $stmt = $this->pdo->prepare(
            'SELECT id, type, deduplication_key, status, attempts, max_attempts, requested_at, claimed_at,
                    completed_at, failure_code, failure_message
             FROM internal_tasks
             WHERE type = :type
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute(['type' => $type]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return array{queued:int,running:int,completed:int,failed:int}
     */
    public function counts(): array
    {
        $counts = [
            'queued' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
        ];
        $rows = $this->pdo->query(
            'SELECT status, COUNT(*) AS task_count
             FROM internal_tasks
             GROUP BY status'
        )->fetchAll();
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['task_count'];
            }
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    public function startExecutorRun(): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO task_queue_executor_runs (status, started_at)
             VALUES (:status, :started_at)'
        );
        $stmt->execute([
            'status' => 'running',
            'started_at' => gmdate('c'),
        ]);

        return $this->requiredExecutorRun((int) $this->pdo->lastInsertId());
    }

    /**
     * @param array{recovered:int,claimed:int,completed:int,continued:int,retried:int,failed:int} $summary
     * @param list<array{id:int,status:string,failure_code:?string}> $taskOutcomes
     * @return array<string, mixed>
     */
    public function completeExecutorRun(int $id, array $summary, array $taskOutcomes): array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE task_queue_executor_runs
             SET status = :status, completed_at = :completed_at, summary_json = :summary_json,
                 task_outcomes_json = :task_outcomes_json
             WHERE id = :id AND status = :running'
        );
        $stmt->execute([
            'status' => 'completed',
            'completed_at' => gmdate('c'),
            'summary_json' => json_encode($summary, JSON_THROW_ON_ERROR),
            'task_outcomes_json' => json_encode($taskOutcomes, JSON_THROW_ON_ERROR),
            'id' => $id,
            'running' => 'running',
        ]);

        return $this->requiredExecutorRun($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function failExecutorRun(int $id, string $failureCode): array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE task_queue_executor_runs
             SET status = :status, completed_at = :completed_at, failure_code = :failure_code
             WHERE id = :id AND status = :running'
        );
        $stmt->execute([
            'status' => 'failed',
            'completed_at' => gmdate('c'),
            'failure_code' => substr($failureCode, 0, 100),
            'id' => $id,
            'running' => 'running',
        ]);

        return $this->requiredExecutorRun($id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentExecutorRuns(int $limit = 25): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, status, started_at, completed_at, failure_code, summary_json, task_outcomes_json
             FROM task_queue_executor_runs
             ORDER BY id DESC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn (array $row): array => $this->hydrateExecutorRun($row), $stmt->fetchAll());
    }

    /**
     * @return array{status:string,last_completed_at:?string}
     */
    public function executorHeartbeatStatus(
        int $freshnessSeconds = self::EXECUTOR_HEARTBEAT_FRESHNESS_SECONDS,
        ?int $now = null,
    ): array {
        $run = $this->recentExecutorRuns(1)[0] ?? null;
        if ($run === null) {
            return ['status' => 'not_observed', 'last_completed_at' => null];
        }

        if ($run['status'] === 'running') {
            return ['status' => 'running', 'last_completed_at' => null];
        }

        if ($run['status'] !== 'completed' || $run['completed_at'] === null) {
            return ['status' => 'failed', 'last_completed_at' => null];
        }

        $completedAt = strtotime((string) $run['completed_at']);
        $referenceTime = $now ?? time();
        if ($completedAt === false || $completedAt < $referenceTime - max(1, $freshnessSeconds)) {
            return ['status' => 'stale', 'last_completed_at' => (string) $run['completed_at']];
        }

        return ['status' => 'fresh', 'last_completed_at' => (string) $run['completed_at']];
    }

    /**
     * @return array<string, mixed>
     */
    public function markCompleted(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE internal_tasks
             SET status = :status, completed_at = :completed_at, failure_code = NULL, failure_message = NULL
             WHERE id = :id AND status = :running'
        );
        $stmt->execute([
            'status' => 'completed',
            'completed_at' => gmdate('c'),
            'id' => $id,
            'running' => 'running',
        ]);

        $task = $this->requiredTask($id);
        $this->resolveAutomaticRecoveryForCompletedTask($id);

        return $task;
    }

    /**
     * Returns a claimed task to the queue without using one of its failure
     * attempts. This is for a deliberately bounded task that has more normal
     * work to continue, not a retry after an error.
     *
     * @return array<string, mixed>
     */
    public function requeueClaimed(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE internal_tasks
             SET status = :status, attempts = MAX(attempts - 1, 0), requested_at = :requested_at,
                 claimed_at = NULL, completed_at = NULL, failure_code = NULL, failure_message = NULL
             WHERE id = :id AND status = :running'
        );
        $stmt->execute([
            'status' => 'queued',
            'requested_at' => gmdate('c'),
            'id' => $id,
            'running' => 'running',
        ]);

        return $this->requiredTask($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function markFailed(int $id, string $failureCode, string $failureMessage, bool $retryable): array
    {
        $task = $this->requiredTask($id);
        if ($task['status'] !== 'running') {
            return $task;
        }

        $willRetry = $retryable && $task['attempts'] < $task['max_attempts'];
        $stmt = $this->pdo->prepare(
            'UPDATE internal_tasks
             SET status = :status, completed_at = :completed_at, failure_code = :failure_code,
                 failure_message = :failure_message, claimed_at = NULL
             WHERE id = :id AND status = :running'
        );
        $stmt->execute([
            'status' => $willRetry ? 'queued' : 'failed',
            'completed_at' => $willRetry ? null : gmdate('c'),
            'failure_code' => substr($failureCode, 0, 100),
            'failure_message' => substr($failureMessage, 0, 500),
            'id' => $id,
            'running' => 'running',
        ]);

        $updated = $this->requiredTask($id);
        if ($updated['status'] === 'failed') {
            $this->openAutomaticRecoveryCircuitForTask($id);
        }

        return $updated;
    }

    public function recoverAbandonedRunning(int $olderThanSeconds = 900): int
    {
        $cutoff = gmdate('c', time() - max(1, $olderThanSeconds));

        return $this->withImmediateTransaction(function () use ($cutoff): int {
            $retry = $this->pdo->prepare(
                'UPDATE internal_tasks
                 SET status = :queued, claimed_at = NULL, failure_code = :failure_code, failure_message = :failure_message
                 WHERE status = :running AND claimed_at < :cutoff AND attempts < max_attempts'
            );
            $retry->execute([
                'queued' => 'queued',
                'failure_code' => 'worker_interrupted',
                'failure_message' => 'A worker claim expired before completion.',
                'running' => 'running',
                'cutoff' => $cutoff,
            ]);
            $recovered = $retry->rowCount();

            $fail = $this->pdo->prepare(
                'UPDATE internal_tasks
                 SET status = :failed, completed_at = :completed_at, failure_code = :failure_code,
                     failure_message = :failure_message
                 WHERE status = :running AND claimed_at < :cutoff AND attempts >= max_attempts'
            );
            $fail->execute([
                'failed' => 'failed',
                'completed_at' => gmdate('c'),
                'failure_code' => 'worker_interrupted',
                'failure_message' => 'A worker claim expired after the final allowed attempt.',
                'running' => 'running',
                'cutoff' => $cutoff,
            ]);

            if ($fail->rowCount() > 0) {
                $openCircuit = $this->pdo->prepare(
                    'UPDATE automatic_recovery_state
                     SET status = :status, updated_at = :updated_at, opened_at = :opened_at
                     WHERE status = :pending AND task_id IN (
                        SELECT id FROM internal_tasks
                        WHERE status = :failed AND failure_code = :failure_code
                     )'
                );
                $now = gmdate('c');
                $openCircuit->execute([
                    'status' => 'blocked',
                    'updated_at' => $now,
                    'opened_at' => $now,
                    'pending' => 'pending',
                    'failed' => 'failed',
                    'failure_code' => 'worker_interrupted',
                ]);
            }

            return $recovered + $fail->rowCount();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOutstanding(string $type, string $deduplicationKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, type, deduplication_key, status, attempts, max_attempts, requested_at, claimed_at,
                    completed_at, failure_code, failure_message
             FROM internal_tasks
             WHERE type = :type AND deduplication_key = :deduplication_key
               AND status IN ("queued", "running")
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'type' => $type,
            'deduplication_key' => $deduplicationKey,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function enqueueWithinTransaction(string $type, string $deduplicationKey, int $maxAttempts): array
    {
        $existing = $this->findOutstanding($type, $deduplicationKey);
        if ($existing !== null) {
            $existing['enqueued'] = false;
            return $existing;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO internal_tasks (type, deduplication_key, status, attempts, max_attempts, requested_at)
             VALUES (:type, :deduplication_key, :status, 0, :max_attempts, :requested_at)'
        );
        $insert->execute([
            'type' => $type,
            'deduplication_key' => $deduplicationKey,
            'status' => 'queued',
            'max_attempts' => $maxAttempts,
            'requested_at' => gmdate('c'),
        ]);

        $task = $this->findById((int) $this->pdo->lastInsertId());
        if ($task === null) {
            throw new \RuntimeException('Unable to load queued task.');
        }

        $task['enqueued'] = true;
        return $task;
    }

    /**
     * @return array{status:string,task_id:?int}
     */
    private function recoveryStateWithinTransaction(string $reason): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT status, task_id FROM automatic_recovery_state WHERE reason = :reason'
        );
        $stmt->execute(['reason' => $reason]);
        $state = $stmt->fetch();

        return $state === false
            ? ['status' => 'idle', 'task_id' => null]
            : ['status' => (string) $state['status'], 'task_id' => $state['task_id'] === null ? null : (int) $state['task_id']];
    }

    private function resolveAutomaticRecoveryForCompletedTask(int $taskId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE automatic_recovery_state
             SET status = :status, task_id = NULL, updated_at = :updated_at, opened_at = NULL
             WHERE task_id = :task_id AND status = :pending'
        );
        $stmt->execute([
            'status' => 'idle',
            'updated_at' => gmdate('c'),
            'task_id' => $taskId,
            'pending' => 'pending',
        ]);
    }

    private function openAutomaticRecoveryCircuitForTask(int $taskId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE automatic_recovery_state
             SET status = :status, updated_at = :updated_at, opened_at = :opened_at
             WHERE task_id = :task_id AND status = :pending'
        );
        $now = gmdate('c');
        $stmt->execute([
            'status' => 'blocked',
            'updated_at' => $now,
            'opened_at' => $now,
            'task_id' => $taskId,
            'pending' => 'pending',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requiredTask(int $id): array
    {
        $task = $this->findById($id);
        if ($task === null) {
            throw new InvalidArgumentException('Task does not exist: ' . $id);
        }

        return $task;
    }

    /**
     * @return array<string, mixed>
     */
    private function requiredExecutorRun(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, status, started_at, completed_at, failure_code, summary_json, task_outcomes_json
             FROM task_queue_executor_runs
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $run = $stmt->fetch();
        if ($run === false) {
            throw new InvalidArgumentException('Executor run does not exist: ' . $id);
        }

        return $this->hydrateExecutorRun($run);
    }

    private function assertAllowedType(string $type): void
    {
        if (!in_array($type, [self::REBUILD_READ_MODEL, self::FAST_SCORE_SWEEP, self::PUBLISH_OFFLINE_SNAPSHOT, self::AGENT_REPLY], true)) {
            throw new InvalidArgumentException('Unsupported internal task type: ' . $type);
        }
    }

    private function assertRecoveryReason(string $reason): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,99}$/', $reason) !== 1) {
            throw new InvalidArgumentException('Invalid automatic recovery reason.');
        }
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS internal_tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                deduplication_key TEXT NOT NULL,
                status TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL,
                requested_at TEXT NOT NULL,
                claimed_at TEXT NULL,
                completed_at TEXT NULL,
                failure_code TEXT NULL,
                failure_message TEXT NULL
            )'
        );
        $this->pdo->exec(
            "CREATE UNIQUE INDEX IF NOT EXISTS internal_tasks_outstanding_unique
             ON internal_tasks (type, deduplication_key)
             WHERE status IN ('queued', 'running')"
        );
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS internal_tasks_claimable
             ON internal_tasks (status, requested_at, id)'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS task_queue_executor_runs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                status TEXT NOT NULL,
                started_at TEXT NOT NULL,
                completed_at TEXT NULL,
                failure_code TEXT NULL,
                summary_json TEXT NOT NULL DEFAULT \'{}\',
                task_outcomes_json TEXT NOT NULL DEFAULT \'[]\'
            )'
        );
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS task_queue_executor_runs_recent
             ON task_queue_executor_runs (id DESC)'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS automatic_recovery_state (
                reason TEXT PRIMARY KEY,
                status TEXT NOT NULL,
                task_id INTEGER NULL,
                updated_at TEXT NOT NULL,
                opened_at TEXT NULL
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS automatic_recovery_launches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reason TEXT NOT NULL,
                task_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL,
                completed_at TEXT NULL,
                UNIQUE (reason, task_id)
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS task_queue_progress_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NOT NULL,
                message TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS task_queue_progress_events_task_recent
             ON task_queue_progress_events (task_id, id DESC)'
        );
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    private function withImmediateTransaction(callable $callback): mixed
    {
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $result = $callback();
            $this->pdo->exec('COMMIT');

            return $result;
        } catch (\Throwable $throwable) {
            try {
                $this->pdo->exec('ROLLBACK');
            } catch (\Throwable) {
                // Preserve the original task-store error when SQLite already
                // ended the transaction after a statement failure.
            }

            throw $throwable;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['attempts'] = (int) $row['attempts'];
        $row['max_attempts'] = (int) $row['max_attempts'];

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateExecutorRun(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['summary'] = json_decode((string) $row['summary_json'], true, 512, JSON_THROW_ON_ERROR);
        $row['task_outcomes'] = json_decode((string) $row['task_outcomes_json'], true, 512, JSON_THROW_ON_ERROR);
        unset($row['summary_json'], $row['task_outcomes_json']);

        return $row;
    }
}
