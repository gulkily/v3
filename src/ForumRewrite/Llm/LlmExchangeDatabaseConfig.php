<?php

declare(strict_types=1);

namespace ForumRewrite\Llm;

final class LlmExchangeDatabaseConfig
{
    /**
     * @param array<string, mixed> $privateConfig
     */
    public static function path(string $projectRoot, array $privateConfig = []): string
    {
        $configuredPath = trim((string) ($privateConfig['LLM_EXCHANGE_DATABASE_PATH'] ?? ''));
        if ($configuredPath !== '') {
            return $configuredPath;
        }

        return rtrim($projectRoot, '/\\') . '/state/private/llm_exchanges.sqlite3';
    }
}
