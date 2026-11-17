<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * A line `withdraw` in a reply to a pledge takes that pledge back. The line stays in
 * the body as written; this class only reads it. `withdraw` and `withdraw:` are the
 * same line, case does not matter, quoted lines are skipped and the first such line
 * in a post is the one read. Whether it takes effect is decided by PledgeCampaign.
 */
final class WithdrawDirective
{
    /** The whole body of a reply that only withdraws, as the Withdraw pledge button sends it. */
    public const BODY = 'withdraw';

    /** @return string|null the text after `withdraw:` (empty for the bare line), or null when there is no such line */
    public static function extract(string $body): ?string
    {
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            if (preg_match('/^\s*withdraw\s*(?::\s*(.*?))?\s*$/i', $line, $match) === 1) {
                return $match[1] ?? '';
            }
        }

        return null;
    }

    /** True when the body is the withdraw line and nothing else, so the reply has nothing more to say. */
    public static function isBare(string $body): bool
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', $body) ?: [], static fn (string $line): bool => trim($line) !== ''));

        return count($lines) === 1 && self::extract($lines[0]) === '';
    }
}
