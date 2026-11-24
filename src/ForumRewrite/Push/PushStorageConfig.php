<?php

declare(strict_types=1);

namespace ForumRewrite\Push;

/**
 * Where push state lives: subscriptions and the VAPID key, under state/private/, outside the
 * content repository, backups and the public export.
 */
final class PushStorageConfig
{
    /** @param array<string, mixed> $privateConfig */
    public static function databasePath(string $projectRoot, array $privateConfig = []): string
    {
        $configured = trim((string) ($privateConfig['PUSH_DATABASE_PATH'] ?? ''));

        return $configured !== '' ? $configured : rtrim($projectRoot, '/\\') . '/state/private/push.sqlite3';
    }

    /** @param array<string, mixed> $privateConfig */
    public static function vapidKeyPath(string $projectRoot, array $privateConfig = []): string
    {
        return dirname(self::databasePath($projectRoot, $privateConfig)) . '/push_vapid.pem';
    }
}
