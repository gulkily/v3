<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use PDO;

final class DatabaseLockProbe
{
    /**
     * Never creates or modifies the database: the write-lock attempt is rolled back immediately.
     *
     * @return array{status:string,detail:string}
     */
    public function probe(string $path, float $busyTimeoutSeconds = 1.0): array
    {
        if (!is_file($path)) {
            return ['status' => 'missing', 'detail' => 'database file does not exist'];
        }

        try {
            $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('PRAGMA busy_timeout=' . max(1, (int) round($busyTimeoutSeconds * 1000)));
        } catch (\Throwable $exception) {
            return ['status' => 'unavailable', 'detail' => $this->message($exception)];
        }

        try {
            $pdo->query('SELECT count(*) FROM sqlite_master')->fetchColumn();
        } catch (\Throwable $exception) {
            return $this->isLockError($exception)
                ? ['status' => 'locked', 'detail' => 'reads blocked by another process (' . $this->message($exception) . ')']
                : ['status' => 'unavailable', 'detail' => $this->message($exception)];
        }

        try {
            $pdo->exec('BEGIN IMMEDIATE');
            $pdo->exec('ROLLBACK');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->exec('ROLLBACK');
            }

            return $this->isLockError($exception)
                ? ['status' => 'locked', 'detail' => 'write lock held by another process; reads still work']
                : ['status' => 'unavailable', 'detail' => $this->message($exception)];
        }

        return ['status' => 'accessible', 'detail' => 'readable and writable'];
    }

    private function isLockError(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'locked') || str_contains($message, 'busy');
    }

    private function message(\Throwable $exception): string
    {
        return trim(preg_replace('/^SQLSTATE\[[^\]]*\]:[^:]*:\s*\d*\s*/', '', $exception->getMessage()) ?? $exception->getMessage());
    }
}
