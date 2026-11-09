<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

final class ReadModelConnection
{
    /** How long a page request waits for a locked read model before showing the busy page. */
    public const PAGE_REQUEST_WAIT_MILLISECONDS = 2000;

    /**
     * @param int|null $waitMilliseconds how long SQLite waits for a lock; null keeps the
     *        driver default (60 seconds), which CLI commands and writers rely on.
     */
    public function __construct(
        private readonly string $databasePath,
        private readonly ?int $waitMilliseconds = null,
    ) {
    }

    public function open(): PDO
    {
        $pdo = new PDO('sqlite:' . $this->databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        if ($this->waitMilliseconds !== null) {
            $pdo->exec('PRAGMA busy_timeout = ' . max(0, $this->waitMilliseconds));
        }

        return $pdo;
    }

    /** True when the error, or one it wraps, is SQLite reporting a locked database. */
    public static function isLocked(\Throwable $throwable): bool
    {
        for ($error = $throwable; $error !== null; $error = $error->getPrevious()) {
            if ($error instanceof \PDOException) {
                $code = $error->errorInfo[1] ?? null;
                if ($code === 5 || $code === 6) {
                    return true;
                }
            }
            $message = $error->getMessage();
            if (str_contains($message, 'database is locked') || str_contains($message, 'database table is locked')) {
                return true;
            }
        }

        return false;
    }
}
