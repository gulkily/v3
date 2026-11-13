<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * Wording and visibility for a post's tagset state (claimed, resolved, closed; open = none).
 * thread_reactions.js mirrors these rules when a tap changes the state without a reload.
 */
final class PostStateView
{
    /** States that fold a reply to its summary line. */
    public const FOLDING = ['resolved', 'closed'];

    public static function folds(?string $state): bool
    {
        return in_array($state, self::FOLDING, true);
    }

    /** "Claimed by ilyag", "Resolved", "Closed"; empty when open. */
    public static function label(?string $state, ?string $claimer = null): string
    {
        if ($state === null || $state === '' || $state === 'open') {
            return '';
        }
        if ($state === 'claimed') {
            $claimer = trim((string) $claimer);
            return $claimer !== '' ? 'Claimed by ' . $claimer : 'Claimed';
        }

        return ucfirst($state);
    }

    public static function buttonLabel(string $tag, ?string $state): string
    {
        return match ($tag) {
            'claimed' => 'Claim',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            'open' => $state === 'claimed' ? 'Unclaim' : 'Reopen',
            default => ucfirst($tag),
        };
    }

    /** An open post offers every state; a claimed one offers Resolved, Closed, Unclaim; a folded one offers Reopen. */
    public static function buttonHidden(string $tag, ?string $state): bool
    {
        if ($state === null || $state === '') {
            return $tag === 'open';
        }
        if (self::folds($state)) {
            return $tag !== 'open';
        }

        return $tag === $state;
    }
}
