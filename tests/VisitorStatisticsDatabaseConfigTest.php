<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Statistics\VisitorStatisticsDatabaseConfig;

final class VisitorStatisticsDatabaseConfigTest
{
    public function testDefaultPathIsPrivateToProjectState(): void
    {
        assertSame('/tmp/forum/state/private/visitor_statistics.sqlite3', VisitorStatisticsDatabaseConfig::path('/tmp/forum'));
    }

    public function testPrivateConfigOverridesDefaultPath(): void
    {
        assertSame('/srv/forum/private-visitors.sqlite3', VisitorStatisticsDatabaseConfig::path('/tmp/forum', [
            'VISITOR_STATISTICS_DATABASE_PATH' => '/srv/forum/private-visitors.sqlite3',
        ]));
    }
}
