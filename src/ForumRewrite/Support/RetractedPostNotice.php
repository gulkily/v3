<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/** The single user-facing wording for an author or operator retraction. */
final class RetractedPostNotice
{
    public const TEXT = 'This post was retracted by its author.';
    public const TITLE = 'Deleted post';
    public const CONFIRM = 'Retract this post?';
    public const DONE = 'Post deleted from the repository. Cached copies may remain until cleanup; history, backups, and copies held by others may still contain it.';

    public static function text(array $post): string
    {
        return ($post['retraction_attribution'] ?? $post['retraction_class'] ?? null) === 'operator'
            ? 'This post was retracted by an operator.' : self::TEXT;
    }
}
