<?php

declare(strict_types=1);
namespace ForumRewrite\ReadModel;

use PDO;

/** Bounded public conversation, independent of identity/profile counting policy. */
final class RecentPostRepository
{
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
        $query = $pdo->prepare("SELECT posts.post_id, posts.thread_id, posts.created_at,
            substr(posts.subject,1,256) AS subject, substr(posts.body,1,1024) AS body, posts.body_format,
            posts.author_label, posts.author_profile_slug, posts.attribution_counted,
            profiles.username_token AS author_username_token, COALESCE(profiles.is_approved,0) AS author_is_approved,
            root.post_id AS context_id, substr(root.subject,1,256) AS context_subject, substr(root.body,1,1024) AS context_body, root.body_format AS context_body_format
            FROM posts LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
            LEFT JOIN posts root ON root.post_id = posts.thread_id AND root.is_hidden = 0 AND root.retracted_at IS NULL
                AND NOT EXISTS (SELECT 1 FROM json_each(root.board_tags_json) WHERE value = 'identity')
            WHERE " . self::eligible($imagesOnly) . " ORDER BY posts.created_at DESC, posts.post_id DESC LIMIT " . ($limit + 1) . " OFFSET $offset");
        $query->execute();
        $items = $query->fetchAll(PDO::FETCH_ASSOC);
        $hasNext = count($items) > $limit;
        return ['items' => array_slice($items, 0, $limit), 'has_next' => $hasNext];
    }

    private static function eligible(bool $imagesOnly): string
    {
        return self::ELIGIBLE . ($imagesOnly ? " AND instr(posts.body, '" . \ForumRewrite\Images\ImageReference::PREFIX . "') > 0" : '');
    }
}
