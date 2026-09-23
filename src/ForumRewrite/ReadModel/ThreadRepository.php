<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

/**
 * The board's own thread listing query - pure PDO, built on
 * ThreadRowSupport for hiding identity/bootstrap/approval-only threads and
 * hydrating rows. Used by the board itself, the tags index and per-tag
 * pages, and Forte's board view alike (6 call sites at the time of
 * extraction). See docs/plans/codebase_cleanup_audit_findings_v1.md,
 * Phase 2 slice 3.
 */
final class ThreadRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function fetchThreads(PDO $pdo): array
    {
        $rows = $pdo->query(
            'SELECT threads.root_post_id, threads.root_post_created_at, threads.last_activity_at, threads.subject, threads.body_preview,
                    threads.reply_count, threads.score_total, threads.board_tags_json, threads.thread_labels_json, posts.author_label, posts.author_profile_slug,
                    posts.body AS root_post_body,
                    posts.post_score_total AS root_post_score_total,
                    profiles.username_token AS author_username_token, COALESCE(profiles.is_approved, 0) AS author_is_approved
             FROM threads
             JOIN posts ON posts.post_id = threads.root_post_id
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             ORDER BY last_activity_at DESC, root_post_id DESC'
        )->fetchAll();

        $rows = array_values(array_filter(
            $rows,
            static fn (array $thread): bool => !ThreadRowSupport::isHiddenBootstrapBoardTagsJson((string) $thread['board_tags_json'])
        ));

        return ThreadRowSupport::hydrateThreadRows($rows);
    }

    /**
     * One thread's own row, in the exact shape fetchThreads() produces -
     * unlike fetchThreads(), this doesn't exclude identity/bootstrap/
     * approval-only threads, since it's for resolving a single thread a
     * caller already knows the id of (a direct permalink), not for
     * populating the board's own listing.
     *
     * @return array<string, mixed>|null
     */
    public static function byId(PDO $pdo, string $threadId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT threads.root_post_id, threads.root_post_created_at, threads.last_activity_at, threads.subject, threads.body_preview,
                    threads.reply_count, threads.score_total, threads.board_tags_json, threads.thread_labels_json, posts.author_label, posts.author_profile_slug,
                    posts.body AS root_post_body,
                    posts.post_score_total AS root_post_score_total,
                    profiles.username_token AS author_username_token, COALESCE(profiles.is_approved, 0) AS author_is_approved
             FROM threads
             JOIN posts ON posts.post_id = threads.root_post_id
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE threads.root_post_id = :root_post_id'
        );
        $stmt->execute(['root_post_id' => $threadId]);
        $thread = $stmt->fetch();

        return $thread === false ? null : ThreadRowSupport::hydrateThreadRow($thread);
    }

    /**
     * Every non-root, visible post across every thread, grouped by
     * thread_id - one query for building reply trees across many threads at
     * once instead of one query per thread.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function allReplyPostsByThreadId(PDO $pdo): array
    {
        $rows = $pdo->query(
            'SELECT posts.post_id, posts.thread_id, posts.parent_id, posts.subject, posts.body, posts.author_identity_id, posts.author_label,
                    posts.created_at, posts.board_tags_json,
                    posts.author_profile_slug, profiles.username_token AS author_username_token,
                    COALESCE(profiles.is_approved, 0) AS author_is_approved,
                    profiles.public_key AS author_public_key
             FROM posts
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE posts.thread_id != posts.post_id
               AND posts.is_hidden = 0
             ORDER BY posts.thread_id ASC, posts.sequence_number ASC'
        )->fetchAll();

        $postsByThreadId = [];
        foreach ($rows as $row) {
            $postsByThreadId[(string) $row['thread_id']][] = $row;
        }

        return $postsByThreadId;
    }
}
