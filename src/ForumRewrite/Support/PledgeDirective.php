<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * A line `pledge: 300` in a reply to a pledge campaign promises that amount toward the
 * goal. The line stays in the body as written; this class only reads it. Quoted lines
 * are skipped and the first `pledge:` line in a post is the one read. Whether it counts
 * is decided by PledgeCampaign.
 */
final class PledgeDirective
{
    /** @return string|null the text after `pledge:`, as written */
    public static function extract(string $body): ?string
    {
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            if (preg_match('/^\s*>/', $line) === 1) {
                continue;
            }
            if (preg_match('/^\s*pledge:\s*(.+?)\s*$/i', $line, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    /** The line a composer writes for an amount. */
    public static function line(string $amount): string
    {
        return 'pledge: ' . trim($amount);
    }
}
