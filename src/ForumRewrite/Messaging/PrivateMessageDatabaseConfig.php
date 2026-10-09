<?php

declare(strict_types=1);

namespace ForumRewrite\Messaging;

final class PrivateMessageDatabaseConfig
{
    /** @param array<string, mixed> $privateConfig */
    public static function path(string $projectRoot, array $privateConfig = []): string
    {
        $configuredPath = trim((string) ($privateConfig['PRIVATE_MESSAGE_DATABASE_PATH'] ?? ''));
        if ($configuredPath !== '') {
            return $configuredPath;
        }

        return rtrim($projectRoot, '/\\') . '/state/private/messages.sqlite3';
    }
}
