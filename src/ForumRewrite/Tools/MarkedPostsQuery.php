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
                $out[$state][] = self::item($row);
            }
            $stmt->closeCursor();
        }

        return $out;
    }

    /**
     * Posts still waiting for work: in a thread carrying one of $parentTags (authored tags or
     * effective labels), with no state, `open`, or `claimed`. Newest first. Each row also has
     * `state` ('open' or 'claimed'); `set_by` names who claimed it.
     *
     * @param list<string> $parentTags tagset parents whose unlocks include a work state, such as `issue`
     * @return list<array{post_id:string,thread_id:string,is_root:bool,thread_subject:string,excerpt:string,set_at:?string,set_by:string,state:string}>
     */
    public static function open(PDO $pdo, array $parentTags, int $limit = 10): array
    {
        $parentTags = array_values(array_unique(array_map('strtolower', array_filter($parentTags, 'is_string'))));
        if ($parentTags === [] || $limit < 1) {
            return [];
        }
        $in = implode(',', array_fill(0, count($parentTags), '?'));
        // Unqualified `threads` so Fastmod's request-local label view, when present, applies.
        $stmt = $pdo->prepare(
            "SELECT posts.post_id, posts.thread_id, posts.body, posts.resolution_state, posts.resolution_set_at,
                    (SELECT COALESCE(NULLIF(profiles.username, ''), profiles.fallback_label) FROM profiles
                     WHERE profiles.identity_id = posts.resolution_set_by) AS set_by,
                    threads.subject AS thread_subject
             FROM posts
             JOIN threads ON threads.root_post_id = COALESCE(posts.thread_id, posts.post_id)
             WHERE posts.is_hidden = 0 AND posts.retracted_at IS NULL AND threads.retracted_at IS NULL
               AND (posts.resolution_state IS NULL OR posts.resolution_state IN ('open', 'claimed'))
               AND (EXISTS (SELECT 1 FROM json_each(threads.board_tags_json) WHERE lower(value) IN ($in))
                    OR EXISTS (SELECT 1 FROM json_each(threads.thread_labels_json) WHERE lower(value) IN ($in)))
             ORDER BY posts.created_at DESC, posts.post_id DESC
             LIMIT ?"
        );
        $stmt->execute([...$parentTags, ...$parentTags, $limit]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = self::item($row) + ['state' => ($row['resolution_state'] ?? '') === 'claimed' ? 'claimed' : 'open'];
        }

        return $out;
    }

    /**
     * Tagset parents that unlock a work state (claimed, resolved or closed): the tags that make a thread hold open work.
     *
     * @param list<array{parent:string,targets:list<string>,unlocks:list<string>,exclusive:bool}> $tagsets
     * @return list<string>
     */
    public static function workParents(array $tagsets): array
    {
        $parents = [];
        foreach ($tagsets as $set) {
            if (array_intersect($set['unlocks'], self::STATES) !== [] && !in_array($set['parent'], $parents, true)) {
                $parents[] = $set['parent'];
            }
        }

        return $parents;
    }

    /**
     * Threads whose marks would lose their buttons if $next replaced $current: a post carries a
     * tag that $current unlocks for it but $next does not. Most marks first.
     *
     * @return list<array{thread_id:string,thread_subject:string,tags:list<string>,marks:int}>
     */
    public static function losingButtons(PDO $pdo, \ForumRewrite\Support\Tagsets $current, \ForumRewrite\Support\Tagsets $next, int $limit = 50): array
    {
        $unlockable = [];
        foreach ($current->all() as $set) {
            foreach ($set['unlocks'] as $tag) {
                $unlockable[$tag] = true;
            }
        }
        if ($unlockable === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($unlockable), '?'));
        // Unqualified `threads` so Fastmod's request-local label view, when present, applies.
        $stmt = $pdo->prepare(
            "SELECT posts.post_id, posts.thread_id, posts.post_tags_json, threads.board_tags_json, threads.thread_labels_json,
                    threads.subject AS thread_subject
             FROM posts
             JOIN threads ON threads.root_post_id = COALESCE(posts.thread_id, posts.post_id)
             WHERE posts.is_hidden = 0 AND posts.retracted_at IS NULL AND threads.retracted_at IS NULL
               AND EXISTS (SELECT 1 FROM json_each(posts.post_tags_json) WHERE lower(value) IN ($in))"
        );
        $stmt->execute(array_keys($unlockable));
        $threads = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $threadTags = array_merge(self::jsonList($row['board_tags_json']), self::jsonList($row['thread_labels_json']));
            $threadId = (string) ($row['thread_id'] ?? '') !== '' ? (string) $row['thread_id'] : (string) $row['post_id'];
            $target = $threadId === (string) $row['post_id'] ? \ForumRewrite\Support\Tagsets::TARGET_ROOT : \ForumRewrite\Support\Tagsets::TARGET_REPLY;
            $lost = array_diff(
                array_intersect(array_map('strtolower', self::jsonList($row['post_tags_json'])), $current->unlockedFor($threadTags, $target)),
                $next->unlockedFor($threadTags, $target),
            );
            if ($lost === []) {
                continue;
            }
            $threads[$threadId] ??= ['thread_id' => $threadId, 'thread_subject' => trim((string) ($row['thread_subject'] ?? '')), 'tags' => [], 'marks' => 0];
            $threads[$threadId]['tags'] = array_values(array_unique([...$threads[$threadId]['tags'], ...$lost]));
            $threads[$threadId]['marks']++;
        }
        $threads = array_values($threads);
        usort($threads, static fn (array $a, array $b): int => [$b['marks'], $a['thread_id']] <=> [$a['marks'], $b['thread_id']]);

        return array_slice($threads, 0, $limit);
    }

    /** @return list<string> */
    private static function jsonList(mixed $json): array
    {
        $list = is_string($json) ? json_decode($json, true) : null;

        return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
    }

    /** @param array<string, mixed> $row */
    private static function item(array $row): array
    {
        $excerpt = trim((string) preg_replace('/\s+/u', ' ', (string) $row['body']));
        if (mb_strlen($excerpt) > 110) {
            $excerpt = rtrim(mb_substr($excerpt, 0, 109)) . '…';
        }
        $threadId = (string) ($row['thread_id'] ?? '') !== '' ? (string) $row['thread_id'] : (string) $row['post_id'];

        return [
            'post_id' => (string) $row['post_id'],
            'thread_id' => $threadId,
            'is_root' => $threadId === (string) $row['post_id'],
            'thread_subject' => trim((string) ($row['thread_subject'] ?? '')),
            'excerpt' => $excerpt,
            'set_at' => $row['resolution_set_at'] !== null ? (string) $row['resolution_set_at'] : null,
            'set_by' => trim((string) ($row['set_by'] ?? '')),
        ];
    }
}
