<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/** The single user-facing wording for a post its author has retracted. */
final class RetractedPostNotice
{
    public const TEXT = 'This post was deleted by its author.';
    public const TITLE = 'Deleted post';
    public const CONFIRM = 'Delete this post? It will stop being shown on this site and replies will stay under a "deleted" placeholder. '
        . 'This does not erase it from the site\'s version history or backups, or from copies other people already saved.';
    public const DONE = 'Post deleted. It is no longer shown on this site; version history, backups, and copies held by others may still contain it.';
}
