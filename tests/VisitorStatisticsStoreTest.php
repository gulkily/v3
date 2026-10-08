<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Statistics\VisitorStatisticsStore;

final class VisitorStatisticsStoreTest
{
    public function testSummarizesHourlyVisitsAndDistinctContributorsWithoutRawFields(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new VisitorStatisticsStore($pdo);
        $day = new \DateTimeImmutable('2026-10-07T12:00:00Z');

        $store->recordVisit($day, 'client-a', 'user-a');
        $store->recordVisit($day, 'client-a', 'user-a');
        $store->recordVisit($day, 'client-b');

        $summary = $store->summary($day);
        $columns = $pdo->query('PRAGMA table_info(visitor_statistics_hourly)')->fetchAll(PDO::FETCH_COLUMN, 1);

        assertSame('available', $summary['status']);
        assertSame(3, $summary['windows'][1]['visits']);
        assertSame(2, $summary['windows'][1]['clients']);
        assertSame(1, $summary['windows'][1]['authenticated_users']);
        assertSame([
            'bucket_start',
            'visit_count',
            'anonymous_visit_count',
            'authenticated_visit_count',
            'client_bitmap',
            'authenticated_user_bitmap',
        ], $columns);
    }

    public function testMergesWindowsExpiresOldBucketsAndSignalsInitialization(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new VisitorStatisticsStore($pdo);
        $asOf = new \DateTimeImmutable('2026-10-07T12:00:00Z');

        assertSame('initializing', $store->summary($asOf)['status']);
        $store->recordVisit($asOf->modify('-91 days'), 'expired-client');
        $store->recordVisit($asOf->modify('-6 days'), 'client-a', 'user-a');
        $store->recordVisit($asOf, 'client-a', 'user-a');

        $summary = $store->summary($asOf);

        assertSame(1, $summary['windows'][1]['visits']);
        assertSame(2, $summary['windows'][7]['visits']);
        assertSame(1, $summary['windows'][7]['clients']);
        assertSame(1, $summary['windows'][7]['authenticated_users']);
        assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM visitor_statistics_hourly')->fetchColumn());
    }

    public function testReportsBoundedHourlyPeriodsAndCollectionStartWithoutLegacyConversion(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec(
            'CREATE TABLE visitor_statistics_daily (
                bucket_date TEXT PRIMARY KEY,
                visit_count INTEGER NOT NULL,
                client_bitmap BLOB NOT NULL,
                authenticated_user_bitmap BLOB NOT NULL
            )'
        );
        $pdo->exec("INSERT INTO visitor_statistics_daily VALUES ('2026-10-01', 99, x'00', x'00')");
        $store = new VisitorStatisticsStore($pdo);
        $asOf = new \DateTimeImmutable('2026-10-07T12:30:00Z');

        assertSame('initializing', $store->summaryForHours($asOf, 24)['status']);

        $store->recordVisit($asOf->modify('-23 hours'), 'client-a', 'user-a');
        $store->recordVisit($asOf->modify('-25 hours'), 'client-b');
        $summary = $store->summaryForHours($asOf, 24);

        assertSame('available', $summary['status']);
        assertSame('2026-10-06T11:00:00Z', $summary['collection_started_at']);
        assertSame('2026-10-06T12:00:00Z', $summary['period_start']);
        assertSame(1, $summary['totals']['visits']);
        assertSame(1, $summary['totals']['clients']);
        assertSame(1, $summary['totals']['authenticated_users']);
        assertSame(1, count($summary['buckets']));
    }

    public function testSeparatesAnonymousAndServerAuthenticatedVisitsWithoutStoringIdentity(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new VisitorStatisticsStore($pdo);
        $asOf = new \DateTimeImmutable('2026-10-07T12:30:00Z');

        $store->recordVisit($asOf, 'client-a');
        $store->recordVisit($asOf, 'client-a', 'openpgp:verified-user');
        $summary = $store->summaryForHours($asOf, 24);
        $row = $pdo->query(
            'SELECT anonymous_visit_count, authenticated_visit_count FROM visitor_statistics_hourly'
        )->fetch();

        assertSame(2, $summary['totals']['visits']);
        assertSame(1, $summary['totals']['anonymous_visits']);
        assertSame(1, $summary['totals']['authenticated_visits']);
        assertSame(['anonymous_visit_count' => 1, 'authenticated_visit_count' => 1], $row);
    }
}
