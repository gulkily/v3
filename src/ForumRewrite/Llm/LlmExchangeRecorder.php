<?php

declare(strict_types=1);

namespace ForumRewrite\Llm;

use PDO;

final class LlmExchangeRecorder
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly bool $enabled = true,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->ensureSchema();
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $request
     * @param array<string, mixed> $response
     * @param array<string, mixed> $error
     */
    public function record(
        array $context,
        array $request,
        array $response,
        string $status,
        float $durationMilliseconds,
        array $error = [],
    ): ?int {
        if (!$this->enabled) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO llm_exchanges (
                occurred_at, call_type, related_post_id, related_content_hash,
                provider, provider_model, provider_request_id, status,
                duration_ms, request_json, response_json, error_json, context_json
             ) VALUES (
                :occurred_at, :call_type, :related_post_id, :related_content_hash,
                :provider, :provider_model, :provider_request_id, :status,
                :duration_ms, :request_json, :response_json, :error_json, :context_json
             )'
        );
        $stmt->execute([
            'occurred_at' => gmdate('c'),
            'call_type' => (string) ($context['call_type'] ?? 'unknown'),
            'related_post_id' => $this->nullableString($context['post_id'] ?? null),
            'related_content_hash' => $this->nullableString($context['content_hash'] ?? null),
            'provider' => $this->nullableString($context['provider'] ?? null),
            'provider_model' => $this->nullableString($context['provider_model'] ?? null),
            'provider_request_id' => $this->nullableString($context['provider_request_id'] ?? null),
            'status' => $status,
            'duration_ms' => $durationMilliseconds,
            'request_json' => $this->encode($request),
            'response_json' => $this->encode($response),
            'error_json' => $this->encode($error),
            'context_json' => $this->encode($context),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS llm_exchanges (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                occurred_at TEXT NOT NULL,
                call_type TEXT NOT NULL,
                related_post_id TEXT NULL,
                related_content_hash TEXT NULL,
                provider TEXT NULL,
                provider_model TEXT NULL,
                provider_request_id TEXT NULL,
                status TEXT NOT NULL,
                duration_ms REAL NOT NULL,
                request_json TEXT NOT NULL,
                response_json TEXT NOT NULL,
                error_json TEXT NOT NULL,
                context_json TEXT NOT NULL
            )'
        );
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS llm_exchanges_occurred_idx
             ON llm_exchanges (occurred_at DESC, id DESC)'
        );
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS llm_exchanges_related_post_idx
             ON llm_exchanges (related_post_id, occurred_at DESC, id DESC)'
        );
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $value */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
