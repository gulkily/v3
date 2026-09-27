<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

use PDO;

final class SqliteFastScoreStore implements FastScoreStore
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->ensureSchema();
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
        return (int) $this->pdo->query("SELECT COUNT(*) FROM fast_score_work WHERE state IN ('pending', 'running')")->fetchColumn() > 0;
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
