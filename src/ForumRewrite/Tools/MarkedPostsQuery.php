<?php

declare(strict_types=1);

namespace ForumRewrite\Tools;

use PDO;

/** Posts that carry a tagset state (claimed, resolved, closed), newest change first, for /tools/tag-unlocks/. */
final class MarkedPostsQuery
{
    public const STATES = ['claimed', 'resolved', 'closed'];

    /**
     * @return array<string, list<array{post_id:string,thread_id:string,is_root:bool,thread_subject:string,excerpt:string,set_at:?string,set_by:string}>>
     *         keyed by state, each list newest first, at most $limit per state
     */
    public static function byState(PDO $pdo, int $limit = 200): array
    {
        $out = array_fill_keys(self::STATES, []);
        $stmt = $pdo->prepare(
            'SELECT posts.post_id, posts.thread_id, posts.body, posts.resolution_state, posts.resolution_set_at,
                    (SELECT COALESCE(NULLIF(profiles.username, \'\'), profiles.fallback_label) FROM profiles
                     WHERE profiles.identity_id = posts.resolution_set_by) AS set_by,
                    threads.subject AS thread_subject
             FROM posts
             LEFT JOIN threads ON threads.root_post_id = COALESCE(posts.thread_id, posts.post_id)
             WHERE posts.resolution_state = :state AND posts.is_hidden = 0 AND posts.retracted_at IS NULL
             ORDER BY posts.resolution_set_at DESC, posts.post_id DESC
             LIMIT :limit'
        );
        foreach (self::STATES as $state) {
            $stmt->bindValue(':state', $state);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $excerpt = trim((string) preg_replace('/\s+/u', ' ', (string) $row['body']));
                if (mb_strlen($excerpt) > 110) {
                    $excerpt = rtrim(mb_substr($excerpt, 0, 109)) . '…';
                }
                $threadId = (string) ($row['thread_id'] ?? '') !== '' ? (string) $row['thread_id'] : (string) $row['post_id'];
                $out[$state][] = [
                    'post_id' => (string) $row['post_id'],
                    'thread_id' => $threadId,
                    'is_root' => $threadId === (string) $row['post_id'],
                    'thread_subject' => trim((string) ($row['thread_subject'] ?? '')),
                    'excerpt' => $excerpt,
                    'set_at' => $row['resolution_set_at'] !== null ? (string) $row['resolution_set_at'] : null,
                    'set_by' => trim((string) ($row['set_by'] ?? '')),
                ];
            }
            $stmt->closeCursor();
        }

        return $out;
    }
}
