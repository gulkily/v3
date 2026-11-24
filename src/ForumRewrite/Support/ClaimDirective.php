<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * A reply in the site's backlog claims thread whose first line is
 * `claim: PF-20261104-B18, PF-20261104-B12` says its author has started those
 * backlog items. The line stays in the body as written; this class only reads
 * it. Only the first line that is not blank is read, so a `claim:` further
 * down, or in a quote, is ordinary text. Whether the claim counts, and who
 * holds an item, is decided by BacklogClaims.
 */
final class ClaimDirective
{
    public const ID_PATTERN = 'PF-\d{8}-[FBO]\d{2,}';

    /** @return list<string>|null the item IDs named, in order and without repeats, or null when the post is not a claim */
    public static function extract(string $body): ?array
    {
        return self::ids(self::firstLine($body), 'claim', false);
    }

    /**
     * The body a claim is posted with.
     *
     * @param list<string> $ids
     */
    public static function body(array $ids): string
    {
        return 'claim: ' . implode(', ', $ids);
    }

    /** True when the body is the claim line and nothing else. */
    public static function isBare(string $body): bool
    {
        return self::extract($body) !== null && self::lineCount($body) === 1;
    }

    /** @internal shared with ReleaseDirective */
    public static function firstLine(string $body): string
    {
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            if (trim($line) !== '') {
                return $line;
            }
        }

        return '';
    }

    /** @internal shared with ReleaseDirective */
    public static function lineCount(string $body): int
    {
        return count(array_filter(preg_split('/\r\n|\r|\n/', $body) ?: [], static fn (string $line): bool => trim($line) !== ''));
    }

    /**
     * Reads `word: ID, ID` (commas or spaces between IDs; case does not matter).
     *
     * @internal shared with ReleaseDirective
     * @return list<string>|null
     */
    public static function ids(string $line, string $word, bool $mayBeBare): ?array
    {
        if (preg_match('/^\s*' . $word . '\s*(?::\s*(.*?))?\s*$/i', $line, $match) !== 1) {
            return null;
        }
        $rest = $match[1] ?? '';
        if ($rest === '') {
            return $mayBeBare ? [] : null;
        }
        $ids = [];
        foreach (preg_split('/[\s,]+/', $rest, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $id = strtoupper($part);
            if (preg_match('/^' . self::ID_PATTERN . '$/', $id) !== 1) {
                return null;
            }
            $ids[$id] = true;
        }

        return array_map('strval', array_keys($ids));
    }
}
