<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * A line `confirm` or `dispute` in a reply to a pledger's fill says whether the delivery
 * arrived. The line stays in the body as written; this class only reads it. The `:` form is
 * the same line, case does not matter, quoted lines are skipped and the first such line in a
 * post is the one read. Whether it counts is decided by PledgeCampaign.
 */
final class ReviewDirective
{
    public const CONFIRM = 'confirm';
    public const DISPUTE = 'dispute';

    /** @return array{word: string, after: string}|null the word read and any text after it, or null when there is no such line */
    public static function extract(string $body): ?array
    {
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            if (preg_match('/^\s*(confirm|dispute)\s*(?::\s*(.*?))?\s*$/i', $line, $match) === 1) {
                return ['word' => strtolower($match[1]), 'after' => $match[2] ?? ''];
            }
        }

        return null;
    }

    /** True when the body is a bare review line and nothing else, so the reply has nothing more to say. */
    public static function isBare(string $body): bool
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', $body) ?: [], static fn (string $line): bool => trim($line) !== ''));

        return count($lines) === 1 && (self::extract($lines[0])['after'] ?? null) === '';
    }
}
