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
}
