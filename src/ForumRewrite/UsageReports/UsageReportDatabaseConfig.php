<?php

declare(strict_types=1);

namespace ForumRewrite\UsageReports;

final class UsageReportDatabaseConfig
{
    /** @param array<string, mixed> $privateConfig */
    public static function path(string $projectRoot, array $privateConfig = []): string
    {
        $configuredPath = trim((string) ($privateConfig['USAGE_REPORTS_DATABASE_PATH'] ?? ''));
        if ($configuredPath !== '') {
            return $configuredPath;
        }

        return rtrim($projectRoot, '/\\') . '/state/private/usage_reports.sqlite3';
    }
}
