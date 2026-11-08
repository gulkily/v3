<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

/**
 * Posts scoring below zero (in practice, flagged) stay out of public listings:
 * board, tags, search, recent, gallery, unread, profiles, QDB browse and the
 * offline snapshot. Activity, operator views and direct links still reach them.
 */
final class ScoreVisibility
{
    public static function listedSql(string $postsAlias = 'posts'): string
    {
        return "COALESCE($postsAlias.post_score_total, 0) >= 0";
    }
}
