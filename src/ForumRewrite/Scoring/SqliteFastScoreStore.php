<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

use PDO;

final class SqliteFastScoreStore implements FastScoreStore
{
    public function __construct(private readonly PDO $pdo, bool $initializeSchema = true)
    {
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        if ($initializeSchema) {
            $this->ensureSchema();
        }
    }

    public function find(string $postId, string $contentHash, string $rubricRevision): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM post_fast_scores WHERE post_id = :post_id AND content_hash = :content_hash AND rubric_revision = :rubric_revision');
        $stmt->execute(['post_id' => $postId, 'content_hash' => $contentHash, 'rubric_revision' => $rubricRevision]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    public function latestScoredForPostContent(string $postId, string $contentHash): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM post_fast_scores
             WHERE post_id = :post_id AND content_hash = :content_hash AND status = 'scored'
             ORDER BY updated_at DESC, rowid DESC
             LIMIT 1"
        );
        $stmt->execute(['post_id' => $postId, 'content_hash' => $contentHash]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    public function save(string $postId, string $contentHash, string $rubricRevision, array $result): array
    {
        $existing = $this->find($postId, $contentHash, $rubricRevision);
        $row = [
            'post_id' => $postId,
            'content_hash' => $contentHash,
            'rubric_revision' => $rubricRevision,
            'status' => (string) ($result['status'] ?? 'unknown'),
            'probability' => isset($result['probability']) && is_numeric($result['probability']) ? (float) $result['probability'] : null,
            'source' => (string) ($result['source'] ?? 'none'),
            'signals_json' => json_encode(array_values(array_filter($result['signals'] ?? [], 'is_string')), JSON_THROW_ON_ERROR),
            'failure_code' => isset($result['failure_code']) ? substr((string) $result['failure_code'], 0, 100) : null,
            'failure_message' => FastScoreFailure::safeMessage(isset($result['failure_code']) ? (string) $result['failure_code'] : null, (string) ($result['status'] ?? 'unknown')),
            'created_at' => (string) ($existing['created_at'] ?? gmdate('c')),
            'updated_at' => gmdate('c'),
        ];
        $stmt = $this->pdo->prepare('INSERT INTO post_fast_scores (post_id, content_hash, rubric_revision, status, probability, source, signals_json, failure_code, failure_message, created_at, updated_at) VALUES (:post_id, :content_hash, :rubric_revision, :status, :probability, :source, :signals_json, :failure_code, :failure_message, :created_at, :updated_at) ON CONFLICT(post_id, content_hash, rubric_revision) DO UPDATE SET status = excluded.status, probability = excluded.probability, source = excluded.source, signals_json = excluded.signals_json, failure_code = excluded.failure_code, failure_message = excluded.failure_message, updated_at = excluded.updated_at');
        $stmt->execute($row);
        return $this->find($postId, $contentHash, $rubricRevision) ?? throw new \RuntimeException('Fastmod score was not saved.');
    }

    /** @return array<string, mixed> */
    public function enqueueWork(string $postId, string $contentHash, string $rubricRevision): array
    {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO fast_score_work (post_id, content_hash, rubric_revision, state, attempt_count, last_attempted_at, next_eligible_at, failure_category, created_at, updated_at)
             VALUES (:post_id, :content_hash, :rubric_revision, :state, 0, NULL, :next_eligible_at, NULL, :created_at, :updated_at)
             ON CONFLICT(post_id, content_hash, rubric_revision) DO NOTHING'
        );
        $stmt->execute([
            'post_id' => $postId,
            'content_hash' => $contentHash,
            'rubric_revision' => $rubricRevision,
            'state' => 'pending',
            'next_eligible_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->requiredWork($postId, $contentHash, $rubricRevision);
    }

    /** @return array<string, mixed>|null */
    public function findWork(string $postId, string $contentHash, string $rubricRevision): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM fast_score_work WHERE post_id = :post_id AND content_hash = :content_hash AND rubric_revision = :rubric_revision');
        $stmt->execute(['post_id' => $postId, 'content_hash' => $contentHash, 'rubric_revision' => $rubricRevision]);
        $work = $stmt->fetch();
        return $work === false ? null : $work;
    }

    /** @return list<array<string, mixed>> */
    public function claimPendingWork(int $limit): array
    {
        $limit = max(1, $limit);
        $now = gmdate('c');
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $select = $this->pdo->prepare(
                'SELECT post_id, content_hash, rubric_revision
                 FROM fast_score_work
                 WHERE state = :state AND next_eligible_at <= :now
                   AND NOT EXISTS (
                       SELECT 1 FROM fastmod_backfill_work AS backfill_work
                       WHERE backfill_work.post_id = fast_score_work.post_id
                         AND backfill_work.content_hash = fast_score_work.content_hash
                         AND backfill_work.rubric_revision = fast_score_work.rubric_revision
                   )
                 ORDER BY created_at ASC, rowid ASC
                 LIMIT :limit'
            );
            $select->bindValue(':state', 'pending');
            $select->bindValue(':now', $now);
            $select->bindValue(':limit', $limit, PDO::PARAM_INT);
            $select->execute();

            $claimed = [];
            foreach ($select->fetchAll() as $key) {
                $update = $this->pdo->prepare(
                    'UPDATE fast_score_work
                     SET state = :running, attempt_count = attempt_count + 1,
                         last_attempted_at = :now, updated_at = :now
                     WHERE post_id = :post_id AND content_hash = :content_hash
                       AND rubric_revision = :rubric_revision AND state = :pending'
                );
                $update->execute([
                    'running' => 'running',
                    'now' => $now,
                    'post_id' => $key['post_id'],
                    'content_hash' => $key['content_hash'],
                    'rubric_revision' => $key['rubric_revision'],
                    'pending' => 'pending',
                ]);
                if ($update->rowCount() === 1) {
                    $claimed[] = $this->requiredWork((string) $key['post_id'], (string) $key['content_hash'], (string) $key['rubric_revision']);
                }
            }
            $this->pdo->exec('COMMIT');
            return $claimed;
        } catch (\Throwable $error) {
            $this->pdo->exec('ROLLBACK');
            throw $error;
        }
    }

    /** @param array<string, mixed> $work @param array<string, mixed> $result */
    public function completeWork(array $work, array $result): array
    {
        $postId = (string) $work['post_id'];
        $contentHash = (string) $work['content_hash'];
        $rubricRevision = (string) $work['rubric_revision'];
        $this->save($postId, $contentHash, $rubricRevision, $result);

        $status = (string) ($result['status'] ?? 'provider_error');
        $attemptCount = (int) ($work['attempt_count'] ?? 1);
        $state = $status;
        $nextEligibleAt = null;
        if (in_array($status, ['provider_error', 'invalid_response'], true) && $attemptCount < 3) {
            $state = 'pending';
            $delay = $attemptCount === 1 ? 300 : 1800;
            $nextEligibleAt = gmdate('c', time() + $delay);
        } elseif (!in_array($status, ['scored', 'excluded'], true)) {
            $state = 'failed';
        }

        $failureCategory = isset($result['failure_code']) ? (string) $result['failure_code'] : ($state === 'failed' ? $status : null);
        $stmt = $this->pdo->prepare(
            'UPDATE fast_score_work
             SET state = :state, next_eligible_at = :next_eligible_at,
                 failure_category = :failure_category, updated_at = :updated_at
             WHERE post_id = :post_id AND content_hash = :content_hash AND rubric_revision = :rubric_revision'
        );
        $stmt->execute([
            'state' => $state,
            'next_eligible_at' => $nextEligibleAt,
            'failure_category' => $failureCategory,
            'updated_at' => gmdate('c'),
            'post_id' => $postId,
            'content_hash' => $contentHash,
            'rubric_revision' => $rubricRevision,
        ]);

        return $this->requiredWork($postId, $contentHash, $rubricRevision);
    }

    public function hasOutstandingWork(): bool
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM fast_score_work
             WHERE state IN ('pending', 'running')
               AND NOT EXISTS (
                   SELECT 1 FROM fastmod_backfill_work AS backfill_work
                   WHERE backfill_work.post_id = fast_score_work.post_id
                     AND backfill_work.content_hash = fast_score_work.content_hash
                     AND backfill_work.rubric_revision = fast_score_work.rubric_revision
               )"
        )->fetchColumn() > 0;
    }

    /**
     * @param list<array{post_id:string,content_hash:string}> $candidates
     * @return array{id:int,requested_count:int,queued_count:int,max_posts:int,max_cost_usd:float,estimated_cost_per_post_usd:float}
     */
    public function createBackfillBatch(
        string $rubricRevision,
        int $maxPosts,
        float $maxCostUsd,
        float $estimatedCostPerPostUsd,
        array $candidates,
    ): array {
        if ($maxPosts < 1 || $maxCostUsd < 0 || $estimatedCostPerPostUsd < 0) {
            throw new \InvalidArgumentException('Backfill limits must be non-negative, with max posts at least 1.');
        }
        $allowedByCost = $estimatedCostPerPostUsd === 0.0
            ? $maxPosts
            : (int) floor($maxCostUsd / $estimatedCostPerPostUsd);
        $allowed = min($maxPosts, $allowedByCost, count($candidates));
        if ($allowed < 1) {
            throw new \InvalidArgumentException('The maximum cost does not cover one estimated Fastmod score.');
        }

        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $now = gmdate('c');
            $insertBatch = $this->pdo->prepare(
                'INSERT INTO fastmod_backfill_batches
                 (rubric_revision, max_posts, max_cost_usd, estimated_cost_per_post_usd, requested_count, queued_count, status, created_at, updated_at)
                 VALUES (:rubric_revision, :max_posts, :max_cost_usd, :estimated_cost_per_post_usd, :requested_count, 0, :status, :created_at, :updated_at)'
            );
            $insertBatch->execute([
                'rubric_revision' => $rubricRevision,
                'max_posts' => $maxPosts,
                'max_cost_usd' => $maxCostUsd,
                'estimated_cost_per_post_usd' => $estimatedCostPerPostUsd,
                'requested_count' => $allowed,
                'status' => 'queued',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $batchId = (int) $this->pdo->lastInsertId();
            $insertWork = $this->pdo->prepare(
                'INSERT INTO fast_score_work (post_id, content_hash, rubric_revision, state, attempt_count, last_attempted_at, next_eligible_at, failure_category, created_at, updated_at)
                 VALUES (:post_id, :content_hash, :rubric_revision, :state, 0, NULL, :next_eligible_at, NULL, :created_at, :updated_at)
                 ON CONFLICT(post_id, content_hash, rubric_revision) DO NOTHING'
            );
            $insertProvenance = $this->pdo->prepare(
                'INSERT INTO fastmod_backfill_work (batch_id, post_id, content_hash, rubric_revision, created_at)
                 VALUES (:batch_id, :post_id, :content_hash, :rubric_revision, :created_at)'
            );
            $queuedCount = 0;
            foreach (array_slice($candidates, 0, $allowed) as $candidate) {
                $insertWork->execute([
                    'post_id' => $candidate['post_id'],
                    'content_hash' => $candidate['content_hash'],
                    'rubric_revision' => $rubricRevision,
                    'state' => 'pending',
                    'next_eligible_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                if ($insertWork->rowCount() !== 1) {
                    continue;
                }
                $insertProvenance->execute([
                    'batch_id' => $batchId,
                    'post_id' => $candidate['post_id'],
                    'content_hash' => $candidate['content_hash'],
                    'rubric_revision' => $rubricRevision,
                    'created_at' => $now,
                ]);
                $queuedCount++;
            }
            $this->pdo->prepare('UPDATE fastmod_backfill_batches SET queued_count = :queued_count, updated_at = :updated_at WHERE id = :id')
                ->execute(['queued_count' => $queuedCount, 'updated_at' => $now, 'id' => $batchId]);
            $this->pdo->exec('COMMIT');
        } catch (\Throwable $error) {
            $this->pdo->exec('ROLLBACK');
            throw $error;
        }

        return [
            'id' => $batchId,
            'requested_count' => $allowed,
            'queued_count' => $queuedCount,
            'max_posts' => $maxPosts,
            'max_cost_usd' => $maxCostUsd,
            'estimated_cost_per_post_usd' => $estimatedCostPerPostUsd,
        ];
    }

    /** @return array<string, int> */
    public function workCounts(): array
    {
        $counts = [];
        foreach ($this->pdo->query('SELECT state, COUNT(*) AS count FROM fast_score_work GROUP BY state')->fetchAll() as $row) {
            $counts[(string) $row['state']] = (int) $row['count'];
        }
        return $counts;
    }

    /** @return array<string, int> */
    public function scoreCounts(): array
    {
        $counts = [];
        foreach ($this->pdo->query('SELECT status, COUNT(*) AS count FROM post_fast_scores GROUP BY status')->fetchAll() as $row) {
            $counts[(string) $row['status']] = (int) $row['count'];
        }
        return $counts;
    }

    /** @return array<string, int> */
    public function scoreCountsBySource(): array
    {
        $counts = [];
        foreach ($this->pdo->query('SELECT source, COUNT(*) AS count FROM post_fast_scores GROUP BY source')->fetchAll() as $row) {
            $counts[(string) $row['source']] = (int) $row['count'];
        }
        return $counts;
    }

    /** @return array<string, mixed>|null */
    public function lastFailure(): ?array
    {
        $row = $this->pdo->query("SELECT post_id, failure_code, failure_message, updated_at FROM post_fast_scores WHERE failure_code IS NOT NULL ORDER BY updated_at DESC, rowid DESC LIMIT 1")->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function recentWork(int $limit = 25): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT work.*, score.status AS score_status, score.probability AS probability, score.source AS source
             FROM fast_score_work AS work
             LEFT JOIN post_fast_scores AS score
               ON score.post_id = work.post_id
              AND score.content_hash = work.content_hash
              AND score.rubric_revision = work.rubric_revision
             ORDER BY work.updated_at DESC, work.rowid DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function retryWork(string $postId, string $contentHash, string $rubricRevision): array
    {
        $stmt = $this->pdo->prepare('UPDATE fast_score_work SET state = :state, attempt_count = 0, last_attempted_at = NULL, next_eligible_at = :now, failure_category = NULL, updated_at = :now WHERE post_id = :post_id AND content_hash = :content_hash AND rubric_revision = :rubric_revision');
        $stmt->execute(['state' => 'pending', 'now' => gmdate('c'), 'post_id' => $postId, 'content_hash' => $contentHash, 'rubric_revision' => $rubricRevision]);
        $work = $this->requiredWork($postId, $contentHash, $rubricRevision);
        $this->audit('retry', $work);
        return $work;
    }

    public function invalidateWork(string $postId, string $contentHash, string $rubricRevision): array
    {
        $stmt = $this->pdo->prepare('UPDATE fast_score_work SET state = :state, next_eligible_at = NULL, updated_at = :now WHERE post_id = :post_id AND content_hash = :content_hash AND rubric_revision = :rubric_revision');
        $stmt->execute(['state' => 'invalidated', 'now' => gmdate('c'), 'post_id' => $postId, 'content_hash' => $contentHash, 'rubric_revision' => $rubricRevision]);
        $work = $this->requiredWork($postId, $contentHash, $rubricRevision);
        $this->audit('invalidate', $work);
        return $work;
    }

    public function pruneBefore(string $cutoff): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM fast_score_work WHERE updated_at < :cutoff');
        $stmt->execute(['cutoff' => $cutoff]);
        $workDeleted = $stmt->rowCount();
        $stmt = $this->pdo->prepare('DELETE FROM post_fast_scores WHERE updated_at < :cutoff');
        $stmt->execute(['cutoff' => $cutoff]);
        return $workDeleted + $stmt->rowCount();
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS fast_score_schema_migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS post_fast_scores (post_id TEXT NOT NULL, content_hash TEXT NOT NULL, rubric_revision TEXT NOT NULL, status TEXT NOT NULL, probability REAL NULL, source TEXT NOT NULL, signals_json TEXT NOT NULL, failure_code TEXT NULL, failure_message TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, PRIMARY KEY (post_id, content_hash, rubric_revision))');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS post_fast_scores_current_idx ON post_fast_scores (post_id, content_hash, rubric_revision, status)');
        if ((int) $this->pdo->query("SELECT COUNT(*) FROM fast_score_schema_migrations WHERE version = 'fast_score_work_v1'")->fetchColumn() === 0) {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS fast_score_work (post_id TEXT NOT NULL, content_hash TEXT NOT NULL, rubric_revision TEXT NOT NULL, state TEXT NOT NULL, attempt_count INTEGER NOT NULL DEFAULT 0, last_attempted_at TEXT NULL, next_eligible_at TEXT NULL, failure_category TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, PRIMARY KEY (post_id, content_hash, rubric_revision))');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS fast_score_work_claimable_idx ON fast_score_work (state, next_eligible_at, created_at, post_id)');
            $insert = $this->pdo->prepare('INSERT INTO fast_score_schema_migrations (version, applied_at) VALUES (:version, :applied_at)');
            $insert->execute(['version' => 'fast_score_work_v1', 'applied_at' => gmdate('c')]);
        }
        if ((int) $this->pdo->query("SELECT COUNT(*) FROM fast_score_schema_migrations WHERE version = 'fast_score_failure_code_v1'")->fetchColumn() === 0) {
            $columns = $this->pdo->query('PRAGMA table_info(post_fast_scores)')->fetchAll();
            $hasFailureCode = false;
            foreach ($columns as $column) {
                $hasFailureCode = $hasFailureCode || (string) $column['name'] === 'failure_code';
            }
            if (!$hasFailureCode) {
                $this->pdo->exec('ALTER TABLE post_fast_scores ADD COLUMN failure_code TEXT NULL');
            }
            $insert = $this->pdo->prepare('INSERT INTO fast_score_schema_migrations (version, applied_at) VALUES (:version, :applied_at)');
            $insert->execute(['version' => 'fast_score_failure_code_v1', 'applied_at' => gmdate('c')]);
        }
        if ((int) $this->pdo->query("SELECT COUNT(*) FROM fast_score_schema_migrations WHERE version = 'fast_score_operator_actions_v1'")->fetchColumn() === 0) {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS fast_score_operator_actions (id INTEGER PRIMARY KEY AUTOINCREMENT, occurred_at TEXT NOT NULL, action TEXT NOT NULL, post_id TEXT NOT NULL, content_hash TEXT NOT NULL, rubric_revision TEXT NOT NULL)');
            $insert = $this->pdo->prepare('INSERT INTO fast_score_schema_migrations (version, applied_at) VALUES (:version, :applied_at)');
            $insert->execute(['version' => 'fast_score_operator_actions_v1', 'applied_at' => gmdate('c')]);
        }
        if ((int) $this->pdo->query("SELECT COUNT(*) FROM fast_score_schema_migrations WHERE version = 'fastmod_backfill_batches_v1'")->fetchColumn() === 0) {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS fastmod_backfill_batches (id INTEGER PRIMARY KEY AUTOINCREMENT, rubric_revision TEXT NOT NULL, max_posts INTEGER NOT NULL, max_cost_usd REAL NOT NULL, estimated_cost_per_post_usd REAL NOT NULL, requested_count INTEGER NOT NULL, queued_count INTEGER NOT NULL, status TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS fastmod_backfill_work (batch_id INTEGER NOT NULL, post_id TEXT NOT NULL, content_hash TEXT NOT NULL, rubric_revision TEXT NOT NULL, created_at TEXT NOT NULL, PRIMARY KEY (batch_id, post_id, content_hash, rubric_revision), UNIQUE (post_id, content_hash, rubric_revision), FOREIGN KEY (batch_id) REFERENCES fastmod_backfill_batches(id))');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS fastmod_backfill_work_batch_idx ON fastmod_backfill_work (batch_id, post_id)');
            $insert = $this->pdo->prepare('INSERT INTO fast_score_schema_migrations (version, applied_at) VALUES (:version, :applied_at)');
            $insert->execute(['version' => 'fastmod_backfill_batches_v1', 'applied_at' => gmdate('c')]);
        }
    }

    /** @return array<string, mixed> */
    private function requiredWork(string $postId, string $contentHash, string $rubricRevision): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM fast_score_work WHERE post_id = :post_id AND content_hash = :content_hash AND rubric_revision = :rubric_revision');
        $stmt->execute(['post_id' => $postId, 'content_hash' => $contentHash, 'rubric_revision' => $rubricRevision]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new \RuntimeException('Fastmod work was not saved.');
        }
        $row['attempt_count'] = (int) $row['attempt_count'];
        return $row;
    }

    /** @param array<string, mixed> $work */
    private function audit(string $action, array $work): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO fast_score_operator_actions (occurred_at, action, post_id, content_hash, rubric_revision) VALUES (:occurred_at, :action, :post_id, :content_hash, :rubric_revision)');
        $stmt->execute([
            'occurred_at' => gmdate('c'),
            'action' => $action,
            'post_id' => $work['post_id'],
            'content_hash' => $work['content_hash'],
            'rubric_revision' => $work['rubric_revision'],
        ]);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function hydrate(array $row): array
    {
        $signals = json_decode((string) $row['signals_json'], true);
        return ['post_id' => (string) $row['post_id'], 'content_hash' => (string) $row['content_hash'], 'rubric_revision' => (string) $row['rubric_revision'], 'status' => (string) $row['status'], 'probability' => $row['probability'] === null ? null : (float) $row['probability'], 'source' => (string) $row['source'], 'signals' => is_array($signals) ? $signals : [], 'failure_code' => $row['failure_code'] === null ? null : (string) $row['failure_code'], 'failure_message' => $row['failure_message'] === null ? null : (string) $row['failure_message'], 'created_at' => (string) $row['created_at'], 'updated_at' => (string) $row['updated_at']];
    }
}
