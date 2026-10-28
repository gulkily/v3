<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

use PDO;

/**
 * Successful signature checks, kept beside the read model so rebuilds and later requests
 * reuse them. Keys hash every verified byte. Failures are not kept: a GPG that could not run
 * looks the same as a bad signature. The file is derived and safe to delete; any failure to
 * open, read or write falls back to checking again.
 */
final class SignatureCheckCache
{
    private static ?string $path = null;
    private static ?PDO $pdo = null;

    public static function useBeside(string $readModelDatabasePath): void
    {
        $path = dirname($readModelDatabasePath) . '/signature_checks.sqlite3';
        if ($path !== self::$path) {
            self::$path = $path;
            self::$pdo = null;
        }
    }

    public static function disable(): void
    {
        self::$path = null;
        self::$pdo = null;
    }

    public static function has(string $key): bool
    {
        try {
            $statement = self::connection()?->prepare('SELECT 1 FROM signature_checks WHERE check_key = ?');

            return $statement !== null && $statement->execute([$key]) && $statement->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function remember(string $key): void
    {
        try {
            self::connection()?->prepare('INSERT OR IGNORE INTO signature_checks (check_key) VALUES (?)')->execute([$key]);
        } catch (\Throwable) {
        }
    }

    private static function connection(): ?PDO
    {
        if (self::$path === null) {
            return null;
        }
        if (self::$pdo === null) {
            if (!is_dir(dirname(self::$path))) {
                self::$path = null;
                return null;
            }
            try {
                $pdo = new PDO('sqlite:' . self::$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo->exec('PRAGMA busy_timeout = 2000');
                $pdo->exec('PRAGMA journal_mode = WAL');
                $pdo->exec('CREATE TABLE IF NOT EXISTS signature_checks (check_key TEXT PRIMARY KEY) WITHOUT ROWID');
                self::$pdo = $pdo;
            } catch (\Throwable) {
                self::$path = null;
                return null;
            }
        }

        return self::$pdo;
    }
}
