<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Statistics\VisitorStatisticsStore;

final class VisitorStatisticsStoreTest
{
    public function testSummarizesDailyVisitsAndDistinctContributorsWithoutRawFields(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new VisitorStatisticsStore($pdo);
        $day = new \DateTimeImmutable('2026-10-07T12:00:00Z');

        $store->recordVisit($day, 'client-a', 'user-a');
        $store->recordVisit($day, 'client-a', 'user-a');
        $store->recordVisit($day, 'client-b');

        $summary = $store->summary($day);
        $columns = $pdo->query('PRAGMA table_info(visitor_statistics_daily)')->fetchAll(PDO::FETCH_COLUMN, 1);

        assertSame('available', $summary['status']);
        assertSame(3, $summary['windows'][1]['visits']);
        assertSame(2, $summary['windows'][1]['clients']);
        assertSame(1, $summary['windows'][1]['authenticated_users']);
        assertSame(['bucket_date', 'visit_count', 'client_bitmap', 'authenticated_user_bitmap'], $columns);
    }

    public function testMergesWindowsExpiresOldBucketsAndSignalsInitialization(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new VisitorStatisticsStore($pdo);
        $asOf = new \DateTimeImmutable('2026-10-07T12:00:00Z');

        assertSame('initializing', $store->summary($asOf)['status']);
        $store->recordVisit($asOf->modify('-33 days'), 'expired-client');
        $store->recordVisit($asOf->modify('-6 days'), 'client-a', 'user-a');
        $store->recordVisit($asOf, 'client-a', 'user-a');

        $summary = $store->summary($asOf);

        assertSame(1, $summary['windows'][1]['visits']);
        assertSame(2, $summary['windows'][7]['visits']);
        assertSame(1, $summary['windows'][7]['clients']);
        assertSame(1, $summary['windows'][7]['authenticated_users']);
        assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM visitor_statistics_daily')->fetchColumn());
    }
}
