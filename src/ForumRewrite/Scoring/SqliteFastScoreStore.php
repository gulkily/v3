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
            'failure_message' => isset($result['failure_message']) ? substr((string) $result['failure_message'], 0, 500) : null,
            'created_at' => (string) ($existing['created_at'] ?? gmdate('c')),
            'updated_at' => gmdate('c'),
        ];
        $stmt = $this->pdo->prepare('INSERT INTO post_fast_scores (post_id, content_hash, rubric_revision, status, probability, source, signals_json, failure_message, created_at, updated_at) VALUES (:post_id, :content_hash, :rubric_revision, :status, :probability, :source, :signals_json, :failure_message, :created_at, :updated_at) ON CONFLICT(post_id, content_hash, rubric_revision) DO UPDATE SET status = excluded.status, probability = excluded.probability, source = excluded.source, signals_json = excluded.signals_json, failure_message = excluded.failure_message, updated_at = excluded.updated_at');
        $stmt->execute($row);
        return $this->find($postId, $contentHash, $rubricRevision) ?? throw new \RuntimeException('Fast score was not saved.');
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS post_fast_scores (post_id TEXT NOT NULL, content_hash TEXT NOT NULL, rubric_revision TEXT NOT NULL, status TEXT NOT NULL, probability REAL NULL, source TEXT NOT NULL, signals_json TEXT NOT NULL, failure_message TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, PRIMARY KEY (post_id, content_hash, rubric_revision))');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS post_fast_scores_current_idx ON post_fast_scores (post_id, content_hash, rubric_revision, status)');
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function hydrate(array $row): array
    {
        $signals = json_decode((string) $row['signals_json'], true);
        return ['post_id' => (string) $row['post_id'], 'content_hash' => (string) $row['content_hash'], 'rubric_revision' => (string) $row['rubric_revision'], 'status' => (string) $row['status'], 'probability' => $row['probability'] === null ? null : (float) $row['probability'], 'source' => (string) $row['source'], 'signals' => is_array($signals) ? $signals : [], 'failure_message' => $row['failure_message'] === null ? null : (string) $row['failure_message'], 'created_at' => (string) $row['created_at'], 'updated_at' => (string) $row['updated_at']];
    }
}
