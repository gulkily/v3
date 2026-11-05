<?php

declare(strict_types=1);
namespace ForumRewrite\ReadModel;

use PDO;
use InvalidArgumentException;

/** Bounded public conversation, independent of identity/profile counting policy. */
final class RecentPostRepository
{
    private const ELIGIBLE = "posts.is_hidden = 0 AND posts.retracted_at IS NULL
        AND COALESCE(json_extract(posts.agent_response_json, '$.type'), '') <> 'scripture_expansion'
        AND NOT EXISTS (SELECT 1 FROM json_each(posts.board_tags_json) WHERE value = 'identity')";

    /**
     * Gallery uses the same posts limited to bodies carrying an image reference;
     * ListThumbnails then keeps only well-formed references with a stored file.
     *
     * @return array{items:array,previous:?array,next:?array}
     */
    public static function page(PDO $pdo, ?array $cursor = null, int $limit = 50, bool $imagesOnly = false): array
    {
        $eligible = self::ELIGIBLE . ($imagesOnly ? " AND instr(posts.body, '" . \ForumRewrite\Images\ImageReference::PREFIX . "') > 0" : '');
        $direction = $cursor['direction'] ?? 'next';
        $boundary = $cursor['boundary'] ?? null;
        if ($cursor !== null && (!in_array($direction, ['next','previous'], true) || !is_array($boundary)
            || count($boundary) !== 2 || !is_string($boundary['created_at'] ?? null) || !is_string($boundary['post_id'] ?? null)
            || $boundary['created_at'] === '' || strlen($boundary['created_at']) > 100
            || $boundary['post_id'] === '' || strlen($boundary['post_id']) > 4096)) {
            throw new InvalidArgumentException('This page position is invalid.');
        }
        $where = $eligible;
        $comparison = static fn (string $operator): string => " AND (posts.created_at, posts.post_id) $operator (:created_at, :post_id)";
        if ($boundary !== null) $where .= $comparison($direction === 'previous' ? '>' : '<');
        $limit = max(1, min(50, $limit));
        $order = $direction === 'previous' ? 'ASC' : 'DESC';
        $query = $pdo->prepare("SELECT posts.post_id, posts.thread_id, posts.created_at,
            substr(posts.subject,1,256) AS subject, substr(posts.body,1,1024) AS body, posts.body_format,
            posts.author_label, posts.author_profile_slug, posts.attribution_counted,
            profiles.username_token AS author_username_token, COALESCE(profiles.is_approved,0) AS author_is_approved,
            root.post_id AS context_id, substr(root.subject,1,256) AS context_subject, substr(root.body,1,1024) AS context_body, root.body_format AS context_body_format
            FROM posts LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
            LEFT JOIN posts root ON root.post_id = posts.thread_id AND root.is_hidden = 0 AND root.retracted_at IS NULL
                AND NOT EXISTS (SELECT 1 FROM json_each(root.board_tags_json) WHERE value = 'identity')
            WHERE $where ORDER BY posts.created_at $order, posts.post_id $order LIMIT $limit");
        $query->execute($boundary ?? []);
        $items = $query->fetchAll(PDO::FETCH_ASSOC);
        if ($direction === 'previous') $items = array_reverse($items);
        $key = static fn (array $row): array => ['created_at' => $row['created_at'], 'post_id' => $row['post_id']];
        $first = $items === [] ? $boundary : $key($items[0]);
        $last = $items === [] ? $boundary : $key($items[count($items)-1]);
        $exists = static function (?array $edge, string $operator) use ($pdo, $comparison, $eligible): bool {
            if ($edge === null) return false;
            $query = $pdo->prepare('SELECT 1 FROM posts WHERE ' . $eligible . $comparison($operator) . ' LIMIT 1');
            $query->execute($edge);
            return $query->fetchColumn() !== false;
        };
        return ['items' => $items, 'previous' => $exists($first, '>') ? $first : null, 'next' => $exists($last, '<') ? $last : null];
    }
}
