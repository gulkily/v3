<?php

declare(strict_types=1);

namespace ForumRewrite\Analysis;

use PDO;

final class SqlitePostAnalysisStore implements PostAnalysisStore
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->ensureSchema();
    }

    public function find(string $postId, string $contentHash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT post_id, content_hash, status, requested_at, completed_at, provider, provider_model,
                    provider_request_id, moderation_json, engagement_json, quality_json, raw_response_json,
                    failure_code, failure_message, retry_after
             FROM post_analyses
             WHERE post_id = :post_id AND content_hash = :content_hash'
        );
        $stmt->execute([
            'post_id' => $postId,
            'content_hash' => $contentHash,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function saveComplete(string $postId, string $contentHash, array $analysis): array
    {
        $now = gmdate('c');
        $row = [
            'post_id' => $postId,
            'content_hash' => $contentHash,
            'status' => 'complete',
            'requested_at' => $now,
            'completed_at' => $now,
            'provider' => (string) ($analysis['provider'] ?? ''),
            'provider_model' => (string) ($analysis['provider_model'] ?? ''),
            'provider_request_id' => isset($analysis['provider_request_id']) ? (string) $analysis['provider_request_id'] : null,
            'moderation_json' => $this->encode($analysis['moderation'] ?? []),
            'engagement_json' => $this->encode($analysis['engagement'] ?? []),
            'quality_json' => $this->encode($analysis['quality'] ?? []),
            'raw_response_json' => $this->encode($analysis['raw_response'] ?? []),
            'failure_code' => null,
            'failure_message' => null,
            'retry_after' => null,
        ];

        $this->upsert($row);

        return $this->hydrate($row);
    }

    public function saveFailed(string $postId, string $contentHash, string $failureCode, string $failureMessage): array
    {
        $now = gmdate('c');
        $row = [
            'post_id' => $postId,
            'content_hash' => $contentHash,
            'status' => 'failed',
            'requested_at' => $now,
            'completed_at' => $now,
            'provider' => null,
            'provider_model' => null,
            'provider_request_id' => null,
            'moderation_json' => $this->encode([]),
            'engagement_json' => $this->encode([]),
            'quality_json' => $this->encode([]),
            'raw_response_json' => $this->encode([]),
            'failure_code' => $failureCode,
            'failure_message' => substr($failureMessage, 0, 500),
            'retry_after' => gmdate('c', time() + 300),
        ];

        $this->upsert($row);

        return $this->hydrate($row);
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS post_analyses (
                post_id TEXT NOT NULL,
                content_hash TEXT NOT NULL,
                status TEXT NOT NULL,
                requested_at TEXT NOT NULL,
                completed_at TEXT NULL,
                provider TEXT NULL,
                provider_model TEXT NULL,
                provider_request_id TEXT NULL,
                moderation_json TEXT NOT NULL,
                engagement_json TEXT NOT NULL,
                quality_json TEXT NOT NULL,
                raw_response_json TEXT NOT NULL,
                failure_code TEXT NULL,
                failure_message TEXT NULL,
                retry_after TEXT NULL,
                PRIMARY KEY (post_id, content_hash)
            )'
        );
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_post_analyses_status ON post_analyses (status, completed_at)');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function upsert(array $row): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO post_analyses (
                post_id, content_hash, status, requested_at, completed_at, provider, provider_model,
                provider_request_id, moderation_json, engagement_json, quality_json, raw_response_json,
                failure_code, failure_message, retry_after
             ) VALUES (
                :post_id, :content_hash, :status, :requested_at, :completed_at, :provider, :provider_model,
                :provider_request_id, :moderation_json, :engagement_json, :quality_json, :raw_response_json,
                :failure_code, :failure_message, :retry_after
             )
             ON CONFLICT(post_id, content_hash) DO UPDATE SET
                status = excluded.status,
                requested_at = excluded.requested_at,
                completed_at = excluded.completed_at,
                provider = excluded.provider,
                provider_model = excluded.provider_model,
                provider_request_id = excluded.provider_request_id,
                moderation_json = excluded.moderation_json,
                engagement_json = excluded.engagement_json,
                quality_json = excluded.quality_json,
                raw_response_json = excluded.raw_response_json,
                failure_code = excluded.failure_code,
                failure_message = excluded.failure_message,
                retry_after = excluded.retry_after'
        );
        $stmt->execute($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        return [
            'post_id' => (string) $row['post_id'],
            'content_hash' => (string) $row['content_hash'],
            'status' => (string) $row['status'],
            'requested_at' => (string) $row['requested_at'],
            'completed_at' => isset($row['completed_at']) ? (string) $row['completed_at'] : null,
            'provider' => isset($row['provider']) ? (string) $row['provider'] : null,
            'provider_model' => isset($row['provider_model']) ? (string) $row['provider_model'] : null,
            'provider_request_id' => isset($row['provider_request_id']) ? (string) $row['provider_request_id'] : null,
            'moderation' => $this->decode((string) $row['moderation_json']),
            'engagement' => $this->decode((string) $row['engagement_json']),
            'quality' => $this->decode((string) $row['quality_json']),
            'raw_response' => $this->decode((string) $row['raw_response_json']),
            'failure_code' => isset($row['failure_code']) ? (string) $row['failure_code'] : null,
            'failure_message' => isset($row['failure_message']) ? (string) $row['failure_message'] : null,
            'retry_after' => isset($row['retry_after']) ? (string) $row['retry_after'] : null,
        ];
    }

    private function encode(mixed $value): string
    {
        return json_encode(is_array($value) ? $value : [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
