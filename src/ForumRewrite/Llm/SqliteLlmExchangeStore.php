<?php

declare(strict_types=1);

namespace ForumRewrite\Llm;

use PDO;

final class SqliteLlmExchangeStore
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM llm_exchanges
             ORDER BY occurred_at DESC, id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn (array $row): array => $this->hydrate($row), $stmt->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM llm_exchanges WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function forPost(string $postId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM llm_exchanges
             WHERE related_post_id = :post_id
             ORDER BY occurred_at DESC, id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':post_id', $postId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn (array $row): array => $this->hydrate($row), $stmt->fetchAll());
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function hydrate(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'occurred_at' => (string) ($row['occurred_at'] ?? ''),
            'call_type' => (string) ($row['call_type'] ?? ''),
            'related_post_id' => $this->nullableString($row['related_post_id'] ?? null),
            'related_content_hash' => $this->nullableString($row['related_content_hash'] ?? null),
            'provider' => $this->nullableString($row['provider'] ?? null),
            'provider_model' => $this->nullableString($row['provider_model'] ?? null),
            'provider_request_id' => $this->nullableString($row['provider_request_id'] ?? null),
            'status' => (string) ($row['status'] ?? ''),
            'duration_ms' => (float) ($row['duration_ms'] ?? 0),
            'request' => $this->decode($row['request_json'] ?? null),
            'response' => $this->decode($row['response_json'] ?? null),
            'error' => $this->decode($row['error_json'] ?? null),
            'context' => $this->decode($row['context_json'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @return array<string, mixed> */
    private function decode(mixed $value): array
    {
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
