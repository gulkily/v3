<?php

declare(strict_types=1);

namespace ForumRewrite\Statistics;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Retains hourly counters and cardinality bitmaps only. It never persists
 * client keys, authenticated identities, request paths, or request headers.
 */
final class VisitorStatisticsStore
{
    private const BITMAP_BITS = 4096;
    private const BITMAP_BYTES = self::BITMAP_BITS / 8;
    private const RETENTION_HOURS = 24 * 90;

    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->ensureSchema();
    }

    public function recordVisit(DateTimeImmutable $occurredAt, string $clientKey, ?string $authenticatedUserKey = null): void
    {
        $hour = $this->hourStart($occurredAt);
        $this->pdo->beginTransaction();

        try {
            $row = $this->rowForHour($hour);
            $clientBitmap = $this->addToBitmap((string) ($row['client_bitmap'] ?? $this->emptyBitmap()), $clientKey);
            $userBitmap = (string) ($row['authenticated_user_bitmap'] ?? $this->emptyBitmap());
            $isAuthenticated = $authenticatedUserKey !== null && $authenticatedUserKey !== '';
            if ($isAuthenticated) {
                $userBitmap = $this->addToBitmap($userBitmap, $authenticatedUserKey);
            }

            $statement = $this->pdo->prepare(
                'INSERT INTO visitor_statistics_hourly (
                    bucket_start, visit_count, anonymous_visit_count, authenticated_visit_count, client_bitmap, authenticated_user_bitmap
                 ) VALUES (
                    :bucket_start, 1, :anonymous_visit_count, :authenticated_visit_count, :client_bitmap, :authenticated_user_bitmap
                 )
                 ON CONFLICT(bucket_start) DO UPDATE SET
                    visit_count = visitor_statistics_hourly.visit_count + 1,
                    anonymous_visit_count = visitor_statistics_hourly.anonymous_visit_count + excluded.anonymous_visit_count,
                    authenticated_visit_count = visitor_statistics_hourly.authenticated_visit_count + excluded.authenticated_visit_count,
                    client_bitmap = excluded.client_bitmap,
                    authenticated_user_bitmap = excluded.authenticated_user_bitmap'
            );
            $statement->execute([
                'bucket_start' => $hour,
                'anonymous_visit_count' => $isAuthenticated ? 0 : 1,
                'authenticated_visit_count' => $isAuthenticated ? 1 : 0,
                'client_bitmap' => $clientBitmap,
                'authenticated_user_bitmap' => $userBitmap,
            ]);
            $this->deleteExpired($hour);
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
        $oldest = $asOf->modify('-' . (max($windows) - 1) . ' days')->format('Y-m-d\T00:00:00\Z');
        $rows = $this->hourlyRowsFrom($oldest);
        $result = [];

        foreach ($windows as $window) {
            $start = $asOf->modify('-' . ($window - 1) . ' days')->format('Y-m-d\T00:00:00\Z');
            $visits = 0;
            $clients = $this->emptyBitmap();
            $users = $this->emptyBitmap();
            foreach ($rows as $row) {
                if ((string) $row['bucket_start'] < $start) {
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

    /**
     * @return array{
     *   status:string,
     *   collection_started_at:?string,
     *   period_start:string,
     *   period_end:string,
     *   totals:array{visits:int,anonymous_visits:int,authenticated_visits:int,clients:int,authenticated_users:int},
     *   buckets:list<array{bucket_start:string,visits:int,anonymous_visits:int,authenticated_visits:int,clients:int,authenticated_users:int}>
     * }
     */
    public function summaryForHours(DateTimeImmutable $asOf, int $hours): array
    {
        if ($hours < 1 || $hours > self::RETENTION_HOURS) {
            throw new \InvalidArgumentException('Visitor-statistics period must fit retained hourly aggregates.');
        }

        $asOf = $asOf->setTimezone(new DateTimeZone('UTC'));
        $periodStart = $asOf->modify('-' . $hours . ' hours')->format('Y-m-d\TH:00:00\Z');
        $rows = $this->hourlyRowsFrom($periodStart);
        $allRows = $this->hourlyRowsFrom('0000-01-01T00:00:00Z');
        $clients = $this->emptyBitmap();
        $users = $this->emptyBitmap();
        $visits = 0;
        $anonymousVisits = 0;
        $authenticatedVisits = 0;
        $buckets = [];

        foreach ($this->displayBuckets($rows, $hours) as $row) {
            $visits += (int) $row['visit_count'];
            $anonymousVisits += (int) $row['anonymous_visit_count'];
            $authenticatedVisits += (int) $row['authenticated_visit_count'];
            $buckets[] = [
                'bucket_start' => (string) $row['bucket_start'],
                'visits' => (int) $row['visit_count'],
                'anonymous_visits' => (int) $row['anonymous_visit_count'],
                'authenticated_visits' => (int) $row['authenticated_visit_count'],
                'clients' => $this->estimateCardinality((string) $row['client_bitmap']),
                'authenticated_users' => $this->estimateCardinality((string) $row['authenticated_user_bitmap']),
            ];
        }

        foreach ($rows as $row) {
            $clients = $this->mergeBitmaps($clients, (string) $row['client_bitmap']);
            $users = $this->mergeBitmaps($users, (string) $row['authenticated_user_bitmap']);
        }

        return [
            'status' => $allRows === [] ? 'initializing' : ((string) $allRows[0]['bucket_start'] > $periodStart ? 'partial' : 'available'),
            'collection_started_at' => $allRows === [] ? null : (string) $allRows[0]['bucket_start'],
            'period_start' => $periodStart,
            'period_end' => $asOf->format('Y-m-d\TH:i:s\Z'),
            'totals' => [
                'visits' => $visits,
                'anonymous_visits' => $anonymousVisits,
                'authenticated_visits' => $authenticatedVisits,
                'clients' => $this->estimateCardinality($clients),
                'authenticated_users' => $this->estimateCardinality($users),
            ],
            'buckets' => $buckets,
        ];
    }

    /** @return array<string, mixed>|null */
    private function rowForHour(string $hour): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT client_bitmap, authenticated_user_bitmap FROM visitor_statistics_hourly WHERE bucket_start = :bucket_start'
        );
        $statement->execute(['bucket_start' => $hour]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    private function hourlyRowsFrom(string $start): array
    {
        $statement = $this->pdo->prepare(
            'SELECT bucket_start, visit_count, anonymous_visit_count, authenticated_visit_count, client_bitmap, authenticated_user_bitmap
             FROM visitor_statistics_hourly WHERE bucket_start >= :start ORDER BY bucket_start ASC'
        );
        $statement->execute(['start' => $start]);

        return $statement->fetchAll();
    }

    private function deleteExpired(string $hour): void
    {
        $cutoff = (new DateTimeImmutable($hour, new DateTimeZone('UTC')))
            ->modify('-' . self::RETENTION_HOURS . ' hours')
            ->format('Y-m-d\TH:00:00\Z');
        $statement = $this->pdo->prepare('DELETE FROM visitor_statistics_hourly WHERE bucket_start < :cutoff');
        $statement->execute(['cutoff' => $cutoff]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function displayBuckets(array $rows, int $hours): array
    {
        if ($hours <= 24) {
            return $rows;
        }

        $days = [];
        foreach ($rows as $row) {
            $bucketStart = substr((string) $row['bucket_start'], 0, 10) . 'T00:00:00Z';
            if (!isset($days[$bucketStart])) {
                $days[$bucketStart] = [
                    'bucket_start' => $bucketStart,
                    'visit_count' => 0,
                    'anonymous_visit_count' => 0,
                    'authenticated_visit_count' => 0,
                    'client_bitmap' => $this->emptyBitmap(),
                    'authenticated_user_bitmap' => $this->emptyBitmap(),
                ];
            }
            $days[$bucketStart]['visit_count'] += (int) $row['visit_count'];
            $days[$bucketStart]['anonymous_visit_count'] += (int) $row['anonymous_visit_count'];
            $days[$bucketStart]['authenticated_visit_count'] += (int) $row['authenticated_visit_count'];
            $days[$bucketStart]['client_bitmap'] = $this->mergeBitmaps(
                $days[$bucketStart]['client_bitmap'],
                (string) $row['client_bitmap'],
            );
            $days[$bucketStart]['authenticated_user_bitmap'] = $this->mergeBitmaps(
                $days[$bucketStart]['authenticated_user_bitmap'],
                (string) $row['authenticated_user_bitmap'],
            );
        }

        return array_values($days);
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
            'CREATE TABLE IF NOT EXISTS visitor_statistics_hourly (
                bucket_start TEXT PRIMARY KEY,
                visit_count INTEGER NOT NULL,
                anonymous_visit_count INTEGER NOT NULL DEFAULT 0,
                authenticated_visit_count INTEGER NOT NULL DEFAULT 0,
                client_bitmap BLOB NOT NULL,
                authenticated_user_bitmap BLOB NOT NULL
            )'
        );
        $this->ensureHourlyCounterColumn('anonymous_visit_count');
        $this->ensureHourlyCounterColumn('authenticated_visit_count');
    }

    private function ensureHourlyCounterColumn(string $column): void
    {
        $columns = $this->pdo->query('PRAGMA table_info(visitor_statistics_hourly)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array($column, $columns, true)) {
            $this->pdo->exec('ALTER TABLE visitor_statistics_hourly ADD COLUMN ' . $column . ' INTEGER NOT NULL DEFAULT 0');
        }
    }

    private function hourStart(DateTimeImmutable $occurredAt): string
    {
        return $occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:00:00\Z');
    }
}
