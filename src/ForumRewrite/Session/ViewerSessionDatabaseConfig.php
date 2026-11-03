<?php

declare(strict_types=1);

namespace ForumRewrite\Session;

use PDO;

/**
 * Sign-in sessions live in their own SQLite file under state/private/, outside the
 * disposable read model and outside every export. Deleting it only signs people out;
 * browsers with a saved key sign back in on their next page load.
 */
final class ViewerSessionDatabaseConfig
{
    /** @param array<string, mixed> $privateConfig */
    public static function path(string $projectRoot, array $privateConfig = []): string
    {
        $configuredPath = trim((string) ($privateConfig['VIEWER_SESSION_DATABASE_PATH'] ?? ''));
        if ($configuredPath !== '') {
            return $configuredPath;
        }

        return rtrim($projectRoot, '/\\') . '/state/private/viewer_sessions.sqlite3';
    }

    /** @param array<string, mixed> $privateConfig */
    public static function open(string $projectRoot, array $privateConfig = []): PDO
    {
        $path = self::path($projectRoot, $privateConfig);
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create the session store directory: ' . $directory);
        }
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');
        return $pdo;
    }
}
