<?php

declare(strict_types=1);

namespace ForumRewrite\TaskQueue;

use InvalidArgumentException;
use PDO;

final class SqliteTaskQueueStore
{
    public const REBUILD_READ_MODEL = 'rebuild_read_model';

    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->ensureSchema();
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

        return $this->withImmediateTransaction(function () use ($type, $deduplicationKey, $maxAttempts): array {
            $existing = $this->findOutstanding($type, $deduplicationKey);
            if ($existing !== null) {
                $existing['enqueued'] = false;
                return $existing;
            }

            $now = gmdate('c');
            $insert = $this->pdo->prepare(
                'INSERT INTO internal_tasks (type, deduplication_key, status, attempts, max_attempts, requested_at)
                 VALUES (:type, :deduplication_key, :status, 0, :max_attempts, :requested_at)'
            );
            $insert->execute([
                'type' => $type,
                'deduplication_key' => $deduplicationKey,
                'status' => 'queued',
                'max_attempts' => $maxAttempts,
                'requested_at' => $now,
            ]);

            $task = $this->findById((int) $this->pdo->lastInsertId());
            if ($task === null) {
                throw new \RuntimeException('Unable to load queued task.');
            }

            $task['enqueued'] = true;
            return $task;
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

        return $this->requiredTask($id);
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
    private function requiredTask(int $id): array
    {
        $task = $this->findById($id);
        if ($task === null) {
            throw new InvalidArgumentException('Task does not exist: ' . $id);
        }

        return $task;
    }

    private function assertAllowedType(string $type): void
    {
        if ($type !== self::REBUILD_READ_MODEL) {
            throw new InvalidArgumentException('Unsupported internal task type: ' . $type);
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
}
