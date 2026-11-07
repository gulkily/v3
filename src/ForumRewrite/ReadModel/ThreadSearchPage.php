<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

/**
 * Board search over top-level threads: the effective title or opening post
 * body contains the query, using the QDB literal match rule. Results follow
 * board eligibility and latest-activity order in numbered pages.
 */
final class ThreadSearchPage
{
    public const SIZE = ThreadKeysetPage::SIZE;

    /** @return array{items:array,has_next:bool} */
    public static function page(PDO $pdo, string $term, int $page = 1): array
    {
        $base = self::base($pdo, $term);
        return $base === null ? ['items' => [], 'has_next' => false] : ThreadKeysetPage::select($pdo, $base, [], $page);
    }

    public static function pageOf(PDO $pdo, string $term, string $threadId): ?int
    {
        $base = self::base($pdo, $term);
        return $base === null ? null : ThreadKeysetPage::pageOf($pdo, $base, [], $threadId);
    }

    private static function base(PDO $pdo, string $term): ?string
    {
        $term = trim($term);
        if ($term === '') return null;
        LiteralTextMatch::register($pdo, 'thread_search_matches', $term);
        return ThreadKeysetPage::listedRootsSql()
            . " AND (thread_search_matches(COALESCE(threads.subject, '')) OR thread_search_matches(posts.body))";
    }
}
