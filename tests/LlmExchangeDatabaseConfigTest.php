<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\LlmExchangeDatabaseConfig;

final class LlmExchangeDatabaseConfigTest
{
    public function testDefaultPathIsPrivateToProjectState(): void
    {
        assertSame(
            '/tmp/forum/state/private/llm_exchanges.sqlite3',
            LlmExchangeDatabaseConfig::path('/tmp/forum')
        );
    }

    public function testPrivateConfigOverridesDefaultPath(): void
    {
        assertSame(
            '/srv/forum/private-llm.sqlite3',
            LlmExchangeDatabaseConfig::path('/tmp/forum', [
                'LLM_EXCHANGE_DATABASE_PATH' => '/srv/forum/private-llm.sqlite3',
            ])
        );
    }
}
