<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

final class FastScoreDatabaseConfig
{
    /**
     * @param array<string, mixed> $privateConfig
     */
    public static function path(string $projectRoot, array $privateConfig = []): string
    {
        $configuredPath = trim((string) ($privateConfig['FAST_SCORING_DATABASE_PATH'] ?? ''));
        if ($configuredPath !== '') {
            return $configuredPath;
        }

        return rtrim($projectRoot, '/\\') . '/state/private/fast_scores.sqlite3';
    }
}
