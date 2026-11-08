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
    /** Counts use the same eligibility predicate as the bounded public identity history. */
    public static function countVisibleIdentity(PDO $pdo, string $identityId, string $kind): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE ' . self::identityEligibility($kind));
        $stmt->execute(['identity' => $identityId]);
        return (int) $stmt->fetchColumn();
    }

    private static function identityEligibility(string $kind): string
    {
        if (!in_array($kind, ['threads', 'posts'], true)) {
            throw new \InvalidArgumentException('Unknown profile history.');
        }
        $where = "posts.author_identity_id = :identity
            AND posts.is_hidden = 0 AND posts.retracted_at IS NULL AND " . ScoreVisibility::listedSql() . "
            AND COALESCE(json_extract(posts.agent_response_json, '$.type'), '') <> 'scripture_expansion'
            AND NOT EXISTS (SELECT 1 FROM json_each(posts.board_tags_json) WHERE value = 'identity')";
        if ($kind === 'threads') {
            $where .= " AND posts.post_id = posts.thread_id
                AND EXISTS (SELECT 1 FROM threads WHERE threads.root_post_id = posts.post_id
                    AND threads.retracted_at IS NULL
                    AND NOT EXISTS (SELECT 1 FROM json_each(threads.board_tags_json) WHERE value = 'identity'))";
        }
        return $where;
    }

    /** @return array{items:array,has_next:bool} Pages are numbered from 1, newest first. */
    public static function visibleIdentityPage(PDO $pdo, string $identityId, string $kind, int $page = 1, int $limit = 50): array
    {
        $where = self::identityEligibility($kind);
        $limit = max(1, min(100, $limit));
        $offset = (max(1, $page) - 1) * $limit;
        $stmt = $pdo->prepare("SELECT posts.*, posts.post_id AS root_post_id,
            posts.created_at AS root_post_created_at, posts.body AS body_preview
            FROM posts WHERE $where ORDER BY posts.created_at DESC, posts.post_id DESC LIMIT " . ($limit + 1) . " OFFSET $offset");
        $stmt->execute(['identity' => $identityId]);
        $items = $stmt->fetchAll();
        $hasNext = count($items) > $limit;
        $items = array_slice($items, 0, $limit);
        foreach ($items as &$row) {
            $row['body_preview'] = \ForumRewrite\Support\PostText::plainPreview((string) $row['body_preview'], $row['body_format'] ?? null);
        }
        unset($row);
        return ['items' => $items, 'has_next' => $hasNext];
    }

    /** The page of an identity's thread or post list holding a post, or null when it is not in that list. */
    public static function visibleIdentityPageOf(PDO $pdo, string $identityId, string $kind, string $postId, int $limit = 50): ?int
    {
        $where = self::identityEligibility($kind);
        $limit = max(1, min(100, $limit));
        $target = $pdo->prepare("SELECT created_at FROM posts WHERE $where AND posts.post_id = :post_id");
        $target->execute(['identity' => $identityId, 'post_id' => $postId]);
        $createdAt = $target->fetchColumn();
        if ($createdAt === false) return null;
        $before = $pdo->prepare("SELECT count(*) FROM posts WHERE $where AND (posts.created_at, posts.post_id) > (:created_at, :post_id)");
        $before->execute(['identity' => $identityId, 'created_at' => $createdAt, 'post_id' => $postId]);
        return intdiv((int) $before->fetchColumn(), $limit) + 1;
    }

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
            'SELECT threads.root_post_id, threads.root_post_created_at, threads.last_activity_at, threads.subject, threads.body_preview, threads.retracted_at, threads.retraction_class, threads.retraction_attribution,
                    threads.reply_count, threads.last_post_id, threads.score_total, threads.board_tags_json, threads.thread_labels_json, posts.author_label, posts.author_profile_slug, posts.attribution_counted,
                    profiles.username_token AS author_username_token, COALESCE(CASE WHEN profiles.retired_at IS NOT NULL THEN profiles.was_approved ELSE profiles.is_approved END, 0) AS author_is_approved
             FROM threads
             JOIN posts ON posts.post_id = threads.root_post_id
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE threads.retracted_at IS NULL
               AND ' . ScoreVisibility::listedSql() . '
               AND threads.root_post_id IN (
                 SELECT post_id FROM posts
                 WHERE post_id = thread_id AND attribution_counted = 1 AND author_identity_id IN (%s)
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
            'SELECT posts.post_id, posts.created_at, posts.thread_id, posts.parent_id, posts.subject, posts.body, posts.body_format, posts.retracted_at, posts.retraction_class, posts.retraction_attribution, posts.author_label,
                    posts.author_profile_slug, posts.attribution_counted, profiles.username_token AS author_username_token,
                    COALESCE(CASE WHEN profiles.retired_at IS NOT NULL THEN profiles.was_approved ELSE profiles.is_approved END, 0) AS author_is_approved, posts.board_tags_json
             FROM posts
             LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE author_identity_id IN (%s)
               AND posts.attribution_counted = 1
               AND posts.is_hidden = 0
               AND ' . ScoreVisibility::listedSql() . '
               AND posts.retracted_at IS NULL
               AND COALESCE(json_extract(posts.agent_response_json, \'$.type\'), \'\') <> \'scripture_expansion\'
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
