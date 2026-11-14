<?php

declare(strict_types=1);

namespace ForumRewrite\UsageReports;

use PDO;

/**
 * Usage reports visitors chose to upload, kept in their own private SQLite file
 * (state/private/usage_reports.sqlite3) apart from forum content and messages,
 * so exports never see them. Reports are kept until an operator removes the file.
 *
 * Upload windows hold a SHA-256 of the client, never a raw address, and are
 * pruned after an hour.
 */
final class UsageReportStore
{
    public const MAX_REPORT_BYTES = 102400;
    public const UPLOADS_PER_HOUR = 10;
    public const MAX_STORE_BYTES = 50 * 1024 * 1024;
    private const WINDOW_SECONDS = 3600;

    public function __construct(
        private readonly PDO $pdo,
        private readonly int $maxReportBytes = self::MAX_REPORT_BYTES,
        private readonly int $uploadsPerHour = self::UPLOADS_PER_HOUR,
        private readonly int $maxStoreBytes = self::MAX_STORE_BYTES,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
        $this->ensureSchema();
    }

    public static function clientHash(string $remoteAddress, string $userAgent): string
    {
        return hash('sha256', 'usage-report-client:' . $remoteAddress . "\0" . $userAgent);
    }

    /** Checks run in the order the visitor's fix depends on: size, then rate, then room. */
    public function add(string $body, ?string $username, string $clientHash, int $now): UsageReportResult
    {
        $bytes = strlen($body);
        if ($bytes > $this->maxReportBytes) {
            return UsageReportResult::refused(UsageReportResult::TOO_LARGE);
        }

        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $this->pruneWindows($now);
            $retryAfter = $this->retryAfter($clientHash, $now);
            if ($retryAfter > 0) {
                $this->pdo->exec('COMMIT');

                return UsageReportResult::refused(UsageReportResult::RATE_LIMITED, $retryAfter);
            }
            if ($this->totalBytes() + $bytes > $this->maxStoreBytes) {
                $this->pdo->exec('COMMIT');

                return UsageReportResult::refused(UsageReportResult::STORE_FULL);
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO usage_reports (received_at, username, body, bytes) VALUES (:received_at, :username, :body, :bytes)'
            );
            $insert->execute([
                'received_at' => $now,
                'username' => $username !== null && $username !== '' ? $username : null,
                'body' => $body,
                'bytes' => $bytes,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare('INSERT INTO usage_report_uploads (client_hash, uploaded_at) VALUES (?, ?)')
                ->execute([$clientHash, $now]);
            $this->pdo->exec('COMMIT');

            return UsageReportResult::stored($id);
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->exec('ROLLBACK');
            }
            throw $exception;
        }
    }

    /** Seconds until this client may upload again; 0 when it may upload now. */
    public function retryAfter(string $clientHash, int $now): int
    {
        $statement = $this->pdo->prepare(
            'SELECT uploaded_at FROM usage_report_uploads WHERE client_hash = ? AND uploaded_at > ? ORDER BY uploaded_at ASC'
        );
        $statement->execute([$clientHash, $now - self::WINDOW_SECONDS]);
        $times = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        if (count($times) < $this->uploadsPerHour) {
            return 0;
        }

        return max(1, $times[count($times) - $this->uploadsPerHour] + self::WINDOW_SECONDS - $now);
    }

    public function totalBytes(): int
    {
        return (int) $this->pdo->query('SELECT COALESCE(SUM(bytes), 0) FROM usage_reports')->fetchColumn();
    }

    /**
     * @param ?string $uploader null for every report, '' for anonymous reports, else one username
     * @return list<array{id:int, received_at:int, username:?string, body:string}>
     */
    public function reports(?string $uploader = null): array
    {
        if ($uploader === null) {
            $statement = $this->pdo->query('SELECT id, received_at, username, body FROM usage_reports ORDER BY id');
        } elseif ($uploader === '') {
            $statement = $this->pdo->query('SELECT id, received_at, username, body FROM usage_reports WHERE username IS NULL ORDER BY id');
        } else {
            $statement = $this->pdo->prepare('SELECT id, received_at, username, body FROM usage_reports WHERE username = ? ORDER BY id');
            $statement->execute([$uploader]);
        }

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'received_at' => (int) $row['received_at'],
            'username' => $row['username'] === null ? null : (string) $row['username'],
            'body' => (string) $row['body'],
        ], $statement->fetchAll());
    }

    /** @return list<array{username:?string, reports:int}> */
    public function uploaders(): array
    {
        $rows = $this->pdo->query(
            'SELECT username, COUNT(*) AS reports FROM usage_reports GROUP BY username ORDER BY username IS NULL, username'
        )->fetchAll();

        return array_map(static fn (array $row): array => [
            'username' => $row['username'] === null ? null : (string) $row['username'],
            'reports' => (int) $row['reports'],
        ], $rows);
    }

    private function pruneWindows(int $now): void
    {
        $this->pdo->prepare('DELETE FROM usage_report_uploads WHERE uploaded_at <= ?')->execute([$now - self::WINDOW_SECONDS]);
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS usage_reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                received_at INTEGER NOT NULL,
                username TEXT,
                body TEXT NOT NULL,
                bytes INTEGER NOT NULL
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS usage_report_uploads (
                client_hash TEXT NOT NULL,
                uploaded_at INTEGER NOT NULL
            )'
        );
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS usage_report_uploads_client ON usage_report_uploads (client_hash, uploaded_at)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS usage_reports_username ON usage_reports (username)');
    }
}
