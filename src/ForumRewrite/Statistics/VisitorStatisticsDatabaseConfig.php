<?php

declare(strict_types=1);

namespace ForumRewrite\Statistics;

final class VisitorStatisticsDatabaseConfig
{
    /** @param array<string, mixed> $privateConfig */
    public static function path(string $projectRoot, array $privateConfig = []): string
    {
        $configuredPath = trim((string) ($privateConfig['VISITOR_STATISTICS_DATABASE_PATH'] ?? ''));
        if ($configuredPath !== '') {
            return $configuredPath;
        }

        return rtrim($projectRoot, '/\\') . '/state/private/visitor_statistics.sqlite3';
    }
}
