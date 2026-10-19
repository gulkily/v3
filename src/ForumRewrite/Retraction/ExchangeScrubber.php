<?php

declare(strict_types=1);
namespace ForumRewrite\Retraction;

use ForumRewrite\Llm\ExchangeDependencies;
use PDO;
use RuntimeException;

/** The retained context is an allowlist, never a copy of provider metadata. */
final class ExchangeScrubber
{
    public static function context(array $dependencies): array
    {
        return ['exchange_dependencies' => ExchangeDependencies::manifest($dependencies), 'retraction_scrubbed' => true];
    }

    public static function isClean(array $row, array $dependencies): bool
    {
        foreach (['request_json', 'response_json', 'error_json'] as $field) {
            if (json_decode((string) ($row[$field] ?? ''), true) !== []) return false;
        }
        $context = json_decode((string) ($row['context_json'] ?? ''), true);
        if (!is_array($context) || ($context['retraction_scrubbed'] ?? false) !== true) return false;
        if (array_diff(array_keys($context), ['exchange_dependencies', 'retraction_scrubbed', 'post_id', 'call_type']) !== []) return false;
        if (isset($context['post_id']) && !in_array($context['post_id'], $dependencies, true)) return false;
        if (isset($context['call_type']) && $context['call_type'] !== 'redacted') return false;
        foreach (['provider', 'provider_model', 'provider_request_id', 'related_content_hash'] as $field) if (($row[$field] ?? null) !== null) return false;
        return ($row['call_type'] ?? '') === 'redacted' && ($row['status'] ?? '') === 'retracted';
    }

    public static function scrub(PDO $pdo, int $id, array $dependencies): void
    {
        $stmt = $pdo->prepare("UPDATE llm_exchanges SET call_type='redacted', related_content_hash=NULL,
            provider=NULL, provider_model=NULL, provider_request_id=NULL, status='retracted', duration_ms=0,
            request_json='{}', response_json='{}', error_json='{}', context_json=:context WHERE id=:id");
        $stmt->execute(['context' => json_encode(self::context($dependencies), JSON_THROW_ON_ERROR), 'id' => $id]);
    }

    public static function prepare(PDO $pdo): void
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA busy_timeout=1000');
        $pdo->exec('PRAGMA secure_delete=ON');
        $mode = strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn());
        if (in_array($mode, ['persist', 'truncate'], true)) {
            if (strtolower((string) $pdo->query('PRAGMA journal_mode=DELETE')->fetchColumn()) !== 'delete') {
                throw new RuntimeException('Exchange journal retirement pending.');
            }
        }
    }

    public static function checkpoint(PDO $pdo): void
    {
        if (strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn()) === 'wal') {
            $row = $pdo->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetch(PDO::FETCH_NUM);
            if ((int) ($row[0] ?? 1) !== 0) throw new RuntimeException('Exchange checkpoint pending; retry after readers finish.');
        }
    }
}
