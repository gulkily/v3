<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/** The single user-facing wording for a post its author has retracted. */
final class RetractedPostNotice
{
    public const TEXT = 'This post was retracted by its author.';
    public const TITLE = 'Deleted post';
    public const CONFIRM = 'Retract this post? It will stop being shown on this site and replies will stay under a "deleted" placeholder. '
        . 'This does not erase it from the site\'s version history or backups, or from copies other people already saved.';
    public const DONE = 'Post retracted. It is no longer shown on this site; version history, backups, and copies held by others may still contain it.';
    public static function text(array $post): string
    {
        return ($post['retraction_class'] ?? null) === 'operator'
            ? 'This post was retracted by an operator.' : self::TEXT;
    }
}
