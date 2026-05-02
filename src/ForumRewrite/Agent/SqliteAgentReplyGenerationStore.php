<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

use PDO;

final class SqliteAgentReplyGenerationStore implements AgentReplyGenerationStore
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->ensureSchema();
    }

    public function findByTarget(string $postId, string $contentHash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, target_post_id, target_content_hash, analysis_hash, status, requested_at, completed_at,
                    provider, provider_model, provider_request_id, response_text, response_style, response_intent,
                    agent_identity_id, agent_profile_slug, agent_post_id, posted_at,
                    failure_code, failure_message, retry_after, raw_response_json
             FROM post_generated_responses
             WHERE target_post_id = :target_post_id AND target_content_hash = :target_content_hash'
        );
        $stmt->execute([
            'target_post_id' => $postId,
            'target_content_hash' => $contentHash,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function saveComplete(array $context, array $generation): array
    {
        $postId = (string) ($context['post_id'] ?? '');
        $contentHash = (string) ($context['content_hash'] ?? '');
        $existing = $this->findByTarget($postId, $contentHash);
        if ($existing !== null && in_array($existing['status'], ['complete', 'posted'], true)) {
            return $existing;
        }

        $now = gmdate('c');
        $row = [
            'target_post_id' => $postId,
            'target_content_hash' => $contentHash,
            'analysis_hash' => (string) ($context['analysis_hash'] ?? ''),
            'status' => 'complete',
            'requested_at' => $now,
            'completed_at' => $now,
            'provider' => (string) ($generation['provider'] ?? ''),
            'provider_model' => (string) ($generation['provider_model'] ?? ''),
            'provider_request_id' => isset($generation['provider_request_id']) ? (string) $generation['provider_request_id'] : null,
            'response_text' => (string) ($generation['response_text'] ?? ''),
            'response_style' => (string) ($generation['response_style'] ?? ''),
            'response_intent' => (string) ($generation['response_intent'] ?? ''),
            'agent_identity_id' => isset($generation['agent_identity_id']) ? (string) $generation['agent_identity_id'] : null,
            'agent_profile_slug' => isset($generation['agent_profile_slug']) ? (string) $generation['agent_profile_slug'] : null,
            'agent_post_id' => isset($generation['agent_post_id']) ? (string) $generation['agent_post_id'] : null,
            'posted_at' => isset($generation['posted_at']) ? (string) $generation['posted_at'] : null,
            'failure_code' => null,
            'failure_message' => null,
            'retry_after' => null,
            'raw_response_json' => $this->encode($generation['raw_response'] ?? []),
        ];
        $this->upsert($row);

        return $this->findByTarget($postId, $contentHash) ?? $this->hydrate($row);
    }

    public function saveFailed(string $postId, string $contentHash, string $analysisHash, string $failureCode, string $failureMessage): array
    {
        $now = gmdate('c');
        $row = [
            'target_post_id' => $postId,
            'target_content_hash' => $contentHash,
            'analysis_hash' => $analysisHash,
            'status' => 'failed',
            'requested_at' => $now,
            'completed_at' => $now,
            'provider' => null,
            'provider_model' => null,
            'provider_request_id' => null,
            'response_text' => null,
            'response_style' => null,
            'response_intent' => null,
            'agent_identity_id' => null,
            'agent_profile_slug' => null,
            'agent_post_id' => null,
            'posted_at' => null,
            'failure_code' => $failureCode,
            'failure_message' => substr($failureMessage, 0, 500),
            'retry_after' => gmdate('c', time() + 300),
            'raw_response_json' => $this->encode([]),
        ];
        $this->upsert($row);

        return $this->findByTarget($postId, $contentHash) ?? $this->hydrate($row);
    }

    public function markPosted(string $postId, string $contentHash, string $agentPostId, string $agentIdentityId, string $agentProfileSlug): array
    {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare(
            'UPDATE post_generated_responses
             SET status = :status,
                 agent_post_id = :agent_post_id,
                 agent_identity_id = :agent_identity_id,
                 agent_profile_slug = :agent_profile_slug,
                 posted_at = :posted_at
             WHERE target_post_id = :target_post_id AND target_content_hash = :target_content_hash'
        );
        $stmt->execute([
            'status' => 'posted',
            'agent_post_id' => $agentPostId,
            'agent_identity_id' => $agentIdentityId,
            'agent_profile_slug' => $agentProfileSlug,
            'posted_at' => $now,
            'target_post_id' => $postId,
            'target_content_hash' => $contentHash,
        ]);

        return $this->findByTarget($postId, $contentHash) ?? [];
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS post_generated_responses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                target_post_id TEXT NOT NULL,
                target_content_hash TEXT NOT NULL,
                analysis_hash TEXT NOT NULL,
                status TEXT NOT NULL,
                requested_at TEXT NOT NULL,
                completed_at TEXT NULL,
                provider TEXT NULL,
                provider_model TEXT NULL,
                provider_request_id TEXT NULL,
                response_text TEXT NULL,
                response_style TEXT NULL,
                response_intent TEXT NULL,
                agent_identity_id TEXT NULL,
                agent_profile_slug TEXT NULL,
                agent_post_id TEXT NULL,
                posted_at TEXT NULL,
                failure_code TEXT NULL,
                failure_message TEXT NULL,
                retry_after TEXT NULL,
                raw_response_json TEXT NOT NULL,
                UNIQUE(target_post_id, target_content_hash)
            )'
        );
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_post_generated_responses_status
             ON post_generated_responses (status, completed_at)'
        );
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_post_generated_responses_agent_post
             ON post_generated_responses (agent_post_id)'
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function upsert(array $row): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO post_generated_responses (
                target_post_id, target_content_hash, analysis_hash, status, requested_at, completed_at,
                provider, provider_model, provider_request_id, response_text, response_style, response_intent,
                agent_identity_id, agent_profile_slug, agent_post_id, posted_at,
                failure_code, failure_message, retry_after, raw_response_json
             ) VALUES (
                :target_post_id, :target_content_hash, :analysis_hash, :status, :requested_at, :completed_at,
                :provider, :provider_model, :provider_request_id, :response_text, :response_style, :response_intent,
                :agent_identity_id, :agent_profile_slug, :agent_post_id, :posted_at,
                :failure_code, :failure_message, :retry_after, :raw_response_json
             )
             ON CONFLICT(target_post_id, target_content_hash) DO UPDATE SET
                analysis_hash = excluded.analysis_hash,
                status = excluded.status,
                requested_at = excluded.requested_at,
                completed_at = excluded.completed_at,
                provider = excluded.provider,
                provider_model = excluded.provider_model,
                provider_request_id = excluded.provider_request_id,
                response_text = excluded.response_text,
                response_style = excluded.response_style,
                response_intent = excluded.response_intent,
                agent_identity_id = excluded.agent_identity_id,
                agent_profile_slug = excluded.agent_profile_slug,
                agent_post_id = excluded.agent_post_id,
                posted_at = excluded.posted_at,
                failure_code = excluded.failure_code,
                failure_message = excluded.failure_message,
                retry_after = excluded.retry_after,
                raw_response_json = excluded.raw_response_json'
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
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'target_post_id' => (string) ($row['target_post_id'] ?? ''),
            'target_content_hash' => (string) ($row['target_content_hash'] ?? ''),
            'analysis_hash' => (string) ($row['analysis_hash'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'requested_at' => (string) ($row['requested_at'] ?? ''),
            'completed_at' => isset($row['completed_at']) ? (string) $row['completed_at'] : null,
            'provider' => isset($row['provider']) ? (string) $row['provider'] : null,
            'provider_model' => isset($row['provider_model']) ? (string) $row['provider_model'] : null,
            'provider_request_id' => isset($row['provider_request_id']) ? (string) $row['provider_request_id'] : null,
            'response_text' => isset($row['response_text']) ? (string) $row['response_text'] : null,
            'response_style' => isset($row['response_style']) ? (string) $row['response_style'] : null,
            'response_intent' => isset($row['response_intent']) ? (string) $row['response_intent'] : null,
            'agent_identity_id' => isset($row['agent_identity_id']) ? (string) $row['agent_identity_id'] : null,
            'agent_profile_slug' => isset($row['agent_profile_slug']) ? (string) $row['agent_profile_slug'] : null,
            'agent_post_id' => isset($row['agent_post_id']) ? (string) $row['agent_post_id'] : null,
            'posted_at' => isset($row['posted_at']) ? (string) $row['posted_at'] : null,
            'failure_code' => isset($row['failure_code']) ? (string) $row['failure_code'] : null,
            'failure_message' => isset($row['failure_message']) ? (string) $row['failure_message'] : null,
            'retry_after' => isset($row['retry_after']) ? (string) $row['retry_after'] : null,
            'raw_response' => $this->decode((string) ($row['raw_response_json'] ?? '{}')),
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
