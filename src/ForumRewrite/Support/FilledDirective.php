<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * A line `filled` in a reply to a pledge says the pledge arrived; `filled: 200` says part
 * of it did. The line stays in the body as written; this class only reads it. `filled`
 * and `filled:` are the same line, case does not matter, quoted lines are skipped and the
 * first such line in a post is the one read. Whether it counts is decided by PledgeCampaign.
 */
final class FilledDirective
{
    /** The whole body of a reply that only fills, as the Mark delivered and Mark filled buttons send it. */
    public const BODY = 'filled';

    /** @return string|null the text after `filled:` (empty for the bare line), or null when there is no such line */
    public static function extract(string $body): ?string
    {
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            if (preg_match('/^\s*filled\s*(?::\s*(.*?))?\s*$/i', $line, $match) === 1) {
                return $match[1] ?? '';
            }
        }

        return null;
    }

    /** True when the body is the bare fill line and nothing else, so the reply has nothing more to say. */
    public static function isBare(string $body): bool
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', $body) ?: [], static fn (string $line): bool => trim($line) !== ''));

        return count($lines) === 1 && self::extract($lines[0]) === '';
    }
}
