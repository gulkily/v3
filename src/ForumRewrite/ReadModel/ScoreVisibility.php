<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

/**
 * Posts scoring below zero (in practice, flagged) stay out of public listings:
 * board, tags, search, recent, gallery, unread, profiles, QDB browse and the
 * offline snapshot. Activity, operator views and direct links still reach them.
 * On qdb with the quote queue on, pending and rejected quotes are unlisted the same way
 * (see QdbModeration); the Queue page and permalinks still reach pending ones.
 */
final class ScoreVisibility
{
    public static function listedSql(string $postsAlias = 'posts'): string
    {
        return self::scoreSql($postsAlias) . \ForumRewrite\Qdb\QdbModeration::current()->listedSqlSuffix($postsAlias);
    }

    /** The score rule alone, for the qdb Queue page, which lists pending quotes on purpose. */
    public static function scoreSql(string $postsAlias = 'posts'): string
    {
        return "COALESCE($postsAlias.post_score_total, 0) >= 0";
    }
}
