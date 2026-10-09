<?php

declare(strict_types=1);

namespace ForumRewrite\Qdb;

final class QdbVoteCaptionDatabaseConfig
{
    public static function path(string $readModelDatabasePath): string
    {
        $configuredPath = trim((string) (getenv('FORUM_QDB_VOTE_CAPTIONS_DATABASE_PATH') ?: ''));
        if ($configuredPath !== '') {
            return $configuredPath;
        }

        return dirname($readModelDatabasePath) . '/qdb_vote_captions.sqlite3';
    }
}
