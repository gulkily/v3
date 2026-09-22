<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;
use PDOStatement;

/**
 * Threads/posts authored by a given set of identities, filtered to what's
 * visible on the public site (hidden-bootstrap board tag excluded, hidden
 * posts excluded). Used by profile pages, the user directory, and the
 * lobby's own-profile view alike. See
 * docs/plans/codebase_cleanup_audit_findings_v1.md, Phase 2 slice 3.
 */
final class AuthoredContentRepository
{
    /**
     * @param list<string> $identityIds
     * @return array<int, array<string, mixed>>
     */
    public static function visibleThreads(PDO $pdo, array $identityIds): array
    {
        if ($identityIds === []) {
            return [];
        }

        $stmt = self::prepareIdentityListQuery(
            $pdo,
            'SELECT threads.root_post_id, threads.root_post_created_at, threads.last_activity_at, threads.subject, threads.body_preview,
                    threads.reply_count, threads.last_post_id, threads.score_total, threads.board_tags_json, threads.thread_labels_json, posts.author_label, posts.author_profile_slug,
                    profiles.username_token AS author_username_token, COALESCE(profiles.is_approved, 0) AS author_is_approved
             FROM threads
             JOIN posts ON posts.post_id = threads.root_post_id
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE threads.root_post_id IN (
                 SELECT post_id FROM posts
                 WHERE post_id = thread_id AND author_identity_id IN (%s)
             )
             ORDER BY last_activity_at DESC, root_post_id ASC',
            $identityIds
        );
        $stmt->execute($identityIds);
        $rows = $stmt->fetchAll();

        $rows = array_values(array_filter(
            $rows,
            static fn (array $thread): bool => !ThreadRowSupport::isHiddenBootstrapBoardTagsJson((string) $thread['board_tags_json'])
        ));

        return ThreadRowSupport::hydrateThreadRows($rows);
    }

    /**
     * @param list<string> $identityIds
     * @return array<int, array<string, mixed>>
     */
    public static function visiblePosts(PDO $pdo, array $identityIds): array
    {
        if ($identityIds === []) {
            return [];
        }

        $stmt = self::prepareIdentityListQuery(
            $pdo,
            'SELECT posts.post_id, posts.created_at, posts.thread_id, posts.parent_id, posts.subject, posts.body, posts.author_label,
                    posts.author_profile_slug, profiles.username_token AS author_username_token,
                    COALESCE(profiles.is_approved, 0) AS author_is_approved, posts.board_tags_json
             FROM posts
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE author_identity_id IN (%s)
               AND posts.is_hidden = 0
             ORDER BY created_at DESC, sequence_number DESC, post_id DESC',
            $identityIds
        );
        $stmt->execute($identityIds);
        $rows = $stmt->fetchAll();

        return array_values(array_filter(
            $rows,
            static fn (array $post): bool => !ThreadRowSupport::isHiddenBootstrapBoardTagsJson((string) $post['board_tags_json'])
        ));
    }

    /**
     * @param list<string> $identityIds
     */
    public static function countVisible(PDO $pdo, array $identityIds, bool $threadsOnly): int
    {
        return count($threadsOnly ? self::visibleThreads($pdo, $identityIds) : self::visiblePosts($pdo, $identityIds));
    }

    /**
     * @param list<string> $identityIds
     */
    private static function prepareIdentityListQuery(PDO $pdo, string $sql, array $identityIds): PDOStatement
    {
        $placeholders = implode(', ', array_fill(0, count($identityIds), '?'));

        return $pdo->prepare(sprintf($sql, $placeholders));
    }
}
