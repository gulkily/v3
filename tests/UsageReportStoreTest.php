<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\UsageReports\UsageReportDatabaseConfig;
use ForumRewrite\UsageReports\UsageReportResult;
use ForumRewrite\UsageReports\UsageReportStore;

final class UsageReportStoreTest
{
    public function testDefaultPathIsPrivateToProjectStateAndConfigOverridesIt(): void
    {
        assertSame('/tmp/forum/state/private/usage_reports.sqlite3', UsageReportDatabaseConfig::path('/tmp/forum'));
        assertSame('/srv/private/usage.sqlite3', UsageReportDatabaseConfig::path('/tmp/forum', [
            'USAGE_REPORTS_DATABASE_PATH' => '/srv/private/usage.sqlite3',
        ]));
    }

    public function testStoresReportsWithUsernameOrAnonymousAndFilters(): void
    {
        $store = new UsageReportStore(new PDO('sqlite::memory:'));
        $client = UsageReportStore::clientHash('203.0.113.5', 'Agent');

        assertTrue($store->add("v1 view /threads/* 0\n", 'ilyag', $client, 1000)->isStored());
        assertTrue($store->add("v1 view / 0\n", null, $client, 1001)->isStored());
        assertTrue($store->add("v1 view /tools/* 0\n", '', $client, 1002)->isStored());

        assertSame(3, count($store->reports()));
        assertSame(['ilyag'], array_column($store->reports('ilyag'), 'username'));
        assertSame([null, null], array_column($store->reports(''), 'username'));
        assertSame([['username' => 'ilyag', 'reports' => 1], ['username' => null, 'reports' => 2]], $store->uploaders());
    }

    public function testRefusesOversizedReportsWithoutCountingThem(): void
    {
        $store = new UsageReportStore(new PDO('sqlite::memory:'), maxReportBytes: 10);
        $result = $store->add(str_repeat('x', 11), null, 'client', 1000);

        assertSame(UsageReportResult::TOO_LARGE, $result->status);
        assertSame(0, $store->totalBytes());
        assertSame(0, $store->retryAfter('client', 1000));
    }

    public function testRateLimitsEachClientPerHourAndReportsRetryAfter(): void
    {
        $store = new UsageReportStore(new PDO('sqlite::memory:'), uploadsPerHour: 2);

        assertTrue($store->add('a', null, 'client', 1000)->isStored());
        assertTrue($store->add('b', null, 'client', 1100)->isStored());
        $limited = $store->add('c', null, 'client', 1200);

        assertSame(UsageReportResult::RATE_LIMITED, $limited->status);
        assertSame(3400, $limited->retryAfterSeconds);
        assertTrue($store->add('d', null, 'other-client', 1200)->isStored());
        assertTrue($store->add('e', null, 'client', 4601)->isStored());
    }

    public function testRefusesUploadsOnceTheStoreIsFullAndKeepsExistingReports(): void
    {
        $store = new UsageReportStore(new PDO('sqlite::memory:'), maxStoreBytes: 5);

        assertTrue($store->add('abc', null, 'a', 1000)->isStored());
        $full = $store->add('def', null, 'b', 1001);

        assertSame(UsageReportResult::STORE_FULL, $full->status);
        assertSame(['abc'], array_column($store->reports(), 'body'));
    }

    public function testStoresOnlyHashedClientKeys(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new UsageReportStore($pdo);
        $store->add('a', null, UsageReportStore::clientHash('198.51.100.7', 'Browser/1'), 1000);

        $dump = json_encode($pdo->query('SELECT * FROM usage_report_uploads')->fetchAll(PDO::FETCH_ASSOC));
        assertFalse(str_contains((string) $dump, '198.51.100.7'));
        assertSame(64, strlen(UsageReportStore::clientHash('198.51.100.7', 'Browser/1')));
    }
}
