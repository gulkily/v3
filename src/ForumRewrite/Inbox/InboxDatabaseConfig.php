<?php

declare(strict_types=1);

namespace ForumRewrite\Inbox;

use PDO;

/**
 * Inbox caught-up marks live in their own SQLite file under state/private/, outside the
 * disposable read model and outside every export. Deleting it resets every member's
 * marks to the moment they next open Inbox; no post or message is lost.
 */
final class InboxDatabaseConfig
{
    public static function path(string $projectRoot): string
    {
        return rtrim($projectRoot, '/\\') . '/state/private/inbox.sqlite3';
    }

    public static function open(string $projectRoot): PDO
    {
        $path = self::path($projectRoot);
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create the Inbox store directory: ' . $directory);
        }
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');
        return $pdo;
    }
}
