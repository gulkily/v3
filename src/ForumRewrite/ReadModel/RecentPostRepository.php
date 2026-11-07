<?php

declare(strict_types=1);
namespace ForumRewrite\ReadModel;

use PDO;

/** Bounded public conversation, independent of identity/profile counting policy. */
final class RecentPostRepository
{
    private const SELECT = "SELECT posts.post_id, posts.thread_id, posts.created_at,
            substr(posts.subject,1,256) AS subject, substr(posts.body,1,1024) AS body, posts.body_format,
            posts.author_label, posts.author_profile_slug, posts.attribution_counted,
            profiles.username_token AS author_username_token, COALESCE(profiles.is_approved,0) AS author_is_approved,
            root.post_id AS context_id, substr(root.subject,1,256) AS context_subject, substr(root.body,1,1024) AS context_body, root.body_format AS context_body_format
            FROM posts LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
            LEFT JOIN posts root ON root.post_id = posts.thread_id AND root.is_hidden = 0 AND root.retracted_at IS NULL
                AND NOT EXISTS (SELECT 1 FROM json_each(root.board_tags_json) WHERE value = 'identity')
            ";

    private const ELIGIBLE = "posts.is_hidden = 0 AND posts.retracted_at IS NULL
        AND COALESCE(json_extract(posts.agent_response_json, '$.type'), '') <> 'scripture_expansion'
        AND NOT EXISTS (SELECT 1 FROM json_each(posts.board_tags_json) WHERE value = 'identity')";

    /**
     * Gallery uses the same posts limited to bodies carrying an image reference;
     * ListThumbnails then keeps only well-formed references with a stored file.
     * Pages are numbered from 1 in (created_at, post_id) descending order.
     *
     * @return array{items:array,has_next:bool}
     */
    public static function page(PDO $pdo, int $page = 1, int $limit = 50, bool $imagesOnly = false): array
    {
        $limit = max(1, min(50, $limit));
        $offset = (max(1, $page) - 1) * $limit;
        $query = $pdo->prepare(self::SELECT . "WHERE " . self::eligible($imagesOnly) . " ORDER BY posts.created_at DESC, posts.post_id DESC LIMIT " . ($limit + 1) . " OFFSET $offset");
        $query->execute();
        $items = $query->fetchAll(PDO::FETCH_ASSOC);
        $hasNext = count($items) > $limit;
        return ['items' => array_slice($items, 0, $limit), 'has_next' => $hasNext];
    }

    /**
     * Eligible posts newer than a time, oldest first, narrowed by an extra condition on `posts`.
     * Inbox uses this for replies and new posts since a member's mark.
     *
     * @param list<string> $params values for the placeholders in $filter
     * @return list<array<string, mixed>>
     */
    public static function since(PDO $pdo, string $after, string $filter, array $params, int $limit): array
    {
        $limit = max(1, min(500, $limit));
        $query = $pdo->prepare(self::SELECT . 'WHERE ' . self::ELIGIBLE . " AND posts.created_at > ? AND ($filter)
            ORDER BY posts.created_at ASC, posts.post_id ASC LIMIT $limit");
        $query->execute([$after, ...$params]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param list<string> $params values for the placeholders in $filter */
    public static function countSince(PDO $pdo, string $after, string $filter, array $params): int
    {
        $query = $pdo->prepare('SELECT count(*) FROM posts WHERE ' . self::ELIGIBLE . " AND posts.created_at > ? AND ($filter)");
        $query->execute([$after, ...$params]);
        return (int) $query->fetchColumn();
    }

    /** The page holding an eligible post, or null when the post is not in this list. */
    public static function pageOf(PDO $pdo, string $postId, int $limit = 50, bool $imagesOnly = false): ?int
    {
        $limit = max(1, min(50, $limit));
        $target = $pdo->prepare('SELECT created_at FROM posts WHERE post_id = :post_id AND ' . self::eligible($imagesOnly));
        $target->execute(['post_id' => $postId]);
        $createdAt = $target->fetchColumn();
        if ($createdAt === false) return null;
        $before = $pdo->prepare('SELECT count(*) FROM posts WHERE ' . self::eligible($imagesOnly)
            . ' AND (posts.created_at, posts.post_id) > (:created_at, :post_id)');
        $before->execute(['created_at' => $createdAt, 'post_id' => $postId]);
        return intdiv((int) $before->fetchColumn(), $limit) + 1;
    }

    private static function eligible(bool $imagesOnly): string
    {
        return self::ELIGIBLE . ($imagesOnly ? " AND instr(posts.body, '" . \ForumRewrite\Images\ImageReference::PREFIX . "') > 0" : '');
    }
}
