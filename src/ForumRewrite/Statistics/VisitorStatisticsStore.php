<?php

declare(strict_types=1);

namespace ForumRewrite\Statistics;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Retains daily counters and cardinality bitmaps only. It never persists
 * client keys, authenticated identities, request paths, or request headers.
 */
final class VisitorStatisticsStore
{
    private const BITMAP_BITS = 4096;
    private const BITMAP_BYTES = self::BITMAP_BITS / 8;
    private const RETENTION_DAYS = 32;

    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->ensureSchema();
    }

    public function recordVisit(DateTimeImmutable $occurredAt, string $clientKey, ?string $authenticatedUserKey = null): void
    {
        $day = $occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
        $this->pdo->beginTransaction();

        try {
            $row = $this->rowForDay($day);
            $clientBitmap = $this->addToBitmap((string) ($row['client_bitmap'] ?? $this->emptyBitmap()), $clientKey);
            $userBitmap = (string) ($row['authenticated_user_bitmap'] ?? $this->emptyBitmap());
            if ($authenticatedUserKey !== null && $authenticatedUserKey !== '') {
                $userBitmap = $this->addToBitmap($userBitmap, $authenticatedUserKey);
            }

            $statement = $this->pdo->prepare(
                'INSERT INTO visitor_statistics_daily (bucket_date, visit_count, client_bitmap, authenticated_user_bitmap)
                 VALUES (:bucket_date, 1, :client_bitmap, :authenticated_user_bitmap)
                 ON CONFLICT(bucket_date) DO UPDATE SET
                    visit_count = visitor_statistics_daily.visit_count + 1,
                    client_bitmap = excluded.client_bitmap,
                    authenticated_user_bitmap = excluded.authenticated_user_bitmap'
            );
            $statement->execute([
                'bucket_date' => $day,
                'client_bitmap' => $clientBitmap,
                'authenticated_user_bitmap' => $userBitmap,
            ]);
            $this->deleteExpired($day);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array{status:string,windows:array<int, array{visits:int,clients:int,authenticated_users:int}>} */
    public function summary(DateTimeImmutable $asOf, array $windows = [1, 7, 30]): array
    {
        $asOf = $asOf->setTimezone(new DateTimeZone('UTC'));
        $oldest = $asOf->modify('-' . (max($windows) - 1) . ' days')->format('Y-m-d');
        $statement = $this->pdo->prepare(
            'SELECT bucket_date, visit_count, client_bitmap, authenticated_user_bitmap
             FROM visitor_statistics_daily WHERE bucket_date >= :oldest ORDER BY bucket_date ASC'
        );
        $statement->execute(['oldest' => $oldest]);
        $rows = $statement->fetchAll();
        $result = [];

        foreach ($windows as $window) {
            $start = $asOf->modify('-' . ($window - 1) . ' days')->format('Y-m-d');
            $visits = 0;
            $clients = $this->emptyBitmap();
            $users = $this->emptyBitmap();
            foreach ($rows as $row) {
                if ((string) $row['bucket_date'] < $start) {
                    continue;
                }
                $visits += (int) $row['visit_count'];
                $clients = $this->mergeBitmaps($clients, (string) $row['client_bitmap']);
                $users = $this->mergeBitmaps($users, (string) $row['authenticated_user_bitmap']);
            }
            $result[(int) $window] = [
                'visits' => $visits,
                'clients' => $this->estimateCardinality($clients),
                'authenticated_users' => $this->estimateCardinality($users),
            ];
        }

        return ['status' => $rows === [] ? 'initializing' : 'available', 'windows' => $result];
    }

    /** @return array<string, mixed>|null */
    private function rowForDay(string $day): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT client_bitmap, authenticated_user_bitmap FROM visitor_statistics_daily WHERE bucket_date = :bucket_date'
        );
        $statement->execute(['bucket_date' => $day]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function deleteExpired(string $day): void
    {
        $cutoff = (new DateTimeImmutable($day, new DateTimeZone('UTC')))
            ->modify('-' . self::RETENTION_DAYS . ' days')
            ->format('Y-m-d');
        $statement = $this->pdo->prepare('DELETE FROM visitor_statistics_daily WHERE bucket_date < :cutoff');
        $statement->execute(['cutoff' => $cutoff]);
    }

    private function addToBitmap(string $bitmap, string $key): string
    {
        $hash = hash('sha256', $key, true);
        $slot = unpack('N', substr($hash, 0, 4))[1] % self::BITMAP_BITS;
        $byte = intdiv($slot, 8);
        $bitmap[$byte] = chr(ord($bitmap[$byte]) | (1 << ($slot % 8)));

        return $bitmap;
    }

    private function mergeBitmaps(string $left, string $right): string
    {
        return $left | $right;
    }

    private function estimateCardinality(string $bitmap): int
    {
        $setBits = 0;
        for ($index = 0; $index < self::BITMAP_BYTES; $index++) {
            $setBits += substr_count(decbin(ord($bitmap[$index])), '1');
        }
        $emptyBits = self::BITMAP_BITS - $setBits;

        return $emptyBits === 0
            ? self::BITMAP_BITS
            : (int) round(-self::BITMAP_BITS * log($emptyBits / self::BITMAP_BITS));
    }

    private function emptyBitmap(): string
    {
        return str_repeat("\0", self::BITMAP_BYTES);
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS visitor_statistics_daily (
                bucket_date TEXT PRIMARY KEY,
                visit_count INTEGER NOT NULL,
                client_bitmap BLOB NOT NULL,
                authenticated_user_bitmap BLOB NOT NULL
            )'
        );
    }
}
