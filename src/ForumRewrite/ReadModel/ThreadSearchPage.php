<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

/**
 * Board search over top-level threads: the effective title or opening post
 * body contains the query, using the QDB literal match rule. Results follow
 * board eligibility and latest-activity order in bounded pages.
 */
final class ThreadSearchPage
{
    public const SIZE = ThreadKeysetPage::SIZE;

    /** The query is hashed so a long query cannot overflow the position token. */
    public static function context(string $term): string
    {
        return 'search:threads:' . substr(hash('sha256', trim($term)), 0, 32) . ':activity:desc';
    }

    /** @return array{items:array,previous:?array,next:?array} */
    public static function page(PDO $pdo, string $term, ?array $boundary = null, string $direction = 'next'): array
    {
        ThreadKeysetPage::validate($boundary, $direction);
        $term = trim($term);
        if ($term === '') {
            return ['items' => [], 'previous' => null, 'next' => null];
        }
        LiteralTextMatch::register($pdo, 'thread_search_matches', $term);
        $base = ThreadKeysetPage::listedRootsSql()
            . " AND (thread_search_matches(COALESCE(threads.subject, '')) OR thread_search_matches(posts.body))";

        return ThreadKeysetPage::select($pdo, $base, [], $boundary, $direction);
    }
}
