<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * A reply to a claim whose first line is `release` gives up everything that
 * claim names; `release: PF-20261104-B18` gives up only the items listed and
 * keeps the rest. Read like ClaimDirective; whether it takes effect is decided
 * by BacklogClaims.
 */
final class ReleaseDirective
{
    /** @return list<string>|null the item IDs released (empty for the bare line, meaning all), or null when there is no release line */
    public static function extract(string $body): ?array
    {
        return ClaimDirective::ids(ClaimDirective::firstLine($body), 'release', true);
    }

    /**
     * The body a release is posted with.
     *
     * @param list<string> $ids none releases the whole claim
     */
    public static function body(array $ids = []): string
    {
        return $ids === [] ? 'release' : 'release: ' . implode(', ', $ids);
    }

    /** True when the body is the release line and nothing else. */
    public static function isBare(string $body): bool
    {
        return self::extract($body) !== null && ClaimDirective::lineCount($body) === 1;
    }
}
