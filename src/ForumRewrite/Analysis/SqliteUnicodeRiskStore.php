<?php

declare(strict_types=1);

namespace ForumRewrite\Analysis;

use PDO;

final class SqliteUnicodeRiskStore implements UnicodeRiskStore
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->ensureSchema();
    }

    public function find(string $postId, string $contentHash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT post_id, content_hash, schema_version, status, deterministic_facts_json, llm_review_json, failure_message, created_at, updated_at
             FROM post_unicode_risks
             WHERE post_id = :post_id AND content_hash = :content_hash'
        );
        $stmt->execute([
            'post_id' => $postId,
            'content_hash' => $contentHash,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function saveDeterministic(string $postId, string $contentHash, int $schemaVersion, array $deterministicFacts): array
    {
        $now = gmdate('c');
        $existing = $this->find($postId, $contentHash);
        $row = [
            'post_id' => $postId,
            'content_hash' => $contentHash,
            'schema_version' => $schemaVersion,
            'status' => 'deterministic_complete',
            'deterministic_facts_json' => $this->encode($deterministicFacts),
            'llm_review_json' => $this->encode([]),
            'failure_message' => null,
            'created_at' => (string) ($existing['created_at'] ?? $now),
            'updated_at' => $now,
        ];

        $this->upsert($row);

        return $this->hydrate($row);
    }

    public function saveLlmReview(string $postId, string $contentHash, array $llmReview): array
    {
        $existing = $this->find($postId, $contentHash);
        if ($existing === null) {
            return $this->saveDeterministic($postId, $contentHash, 1, []);
        }

        $row = [
            'post_id' => $postId,
            'content_hash' => $contentHash,
            'schema_version' => (int) $existing['schema_version'],
            'status' => 'complete',
            'deterministic_facts_json' => $this->encode($existing['deterministic_facts']),
            'llm_review_json' => $this->encode($llmReview),
            'failure_message' => null,
            'created_at' => (string) $existing['created_at'],
            'updated_at' => gmdate('c'),
        ];

        $this->upsert($row);

        return $this->hydrate($row);
    }

    public function saveLlmFailure(string $postId, string $contentHash, string $failureMessage): array
    {
        $existing = $this->find($postId, $contentHash);
        if ($existing === null) {
            return $this->saveDeterministic($postId, $contentHash, 1, []);
        }

        $row = [
            'post_id' => $postId,
            'content_hash' => $contentHash,
            'schema_version' => (int) $existing['schema_version'],
            'status' => 'llm_failed',
            'deterministic_facts_json' => $this->encode($existing['deterministic_facts']),
            'llm_review_json' => $this->encode($existing['llm_review']),
            'failure_message' => substr($failureMessage, 0, 500),
            'created_at' => (string) $existing['created_at'],
            'updated_at' => gmdate('c'),
        ];

        $this->upsert($row);

        return $this->hydrate($row);
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS post_unicode_risks (
                post_id TEXT NOT NULL,
                content_hash TEXT NOT NULL,
                schema_version INTEGER NOT NULL,
                status TEXT NOT NULL,
                deterministic_facts_json TEXT NOT NULL,
                llm_review_json TEXT NOT NULL DEFAULT \'{}\',
                failure_message TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                PRIMARY KEY (post_id, content_hash)
            )'
        );
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_post_unicode_risks_status ON post_unicode_risks (status, updated_at)');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function upsert(array $row): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO post_unicode_risks (
                post_id, content_hash, schema_version, status, deterministic_facts_json, llm_review_json, failure_message, created_at, updated_at
             ) VALUES (
                :post_id, :content_hash, :schema_version, :status, :deterministic_facts_json, :llm_review_json, :failure_message, :created_at, :updated_at
             )
             ON CONFLICT(post_id, content_hash) DO UPDATE SET
                schema_version = excluded.schema_version,
                status = excluded.status,
                deterministic_facts_json = excluded.deterministic_facts_json,
                llm_review_json = excluded.llm_review_json,
                failure_message = excluded.failure_message,
                updated_at = excluded.updated_at'
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
            'schema_version' => (int) $row['schema_version'],
            'status' => (string) $row['status'],
            'deterministic_facts' => $this->decode((string) $row['deterministic_facts_json']),
            'llm_review' => $this->decode((string) $row['llm_review_json']),
            'failure_message' => isset($row['failure_message']) ? (string) $row['failure_message'] : null,
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    private function encode(mixed $value): string
    {
        return json_encode(is_array($value) ? $value : [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
