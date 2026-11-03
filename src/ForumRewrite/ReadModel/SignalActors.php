<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

/**
 * Who stands behind the visible signals on a thread page: eligible likers and flaggers per
 * post, eligible likers of the thread, and the identity that set the current title.
 * Reads only read-model rows; identical for every viewer.
 */
final class SignalActors
{
    /**
     * @param list<string> $postIds the root post and every reply shown
     * @return array{
     *   thread_likes: list<array{username: string, profile_slug: ?string, identity_id: string}>,
     *   posts: array<string, array{like: list<array{username: string, profile_slug: ?string, identity_id: string}>, flag: list<array{username: string, profile_slug: ?string, identity_id: string}>}>,
     *   title_author: ?array{username: string, profile_slug: ?string, identity_id: string}
     * }
     */
    public static function load(PDO $pdo, string $threadId, array $postIds): array
    {
        $threshold = SignalEligibility::threshold($pdo);
        $result = ['thread_likes' => [], 'posts' => [], 'title_author' => null];
        foreach ($postIds as $postId) {
            $result['posts'][$postId] = ['like' => [], 'flag' => []];
        }

        foreach (self::rows($pdo, 'thread-labels', [$threadId], ['like'], $threshold) as $row) {
            $result['thread_likes'][] = self::actor($row);
        }
        if ($postIds !== []) {
            foreach (self::rows($pdo, 'post-reactions', $postIds, ['like', 'flag'], $threshold) as $row) {
                $result['posts'][(string) $row['target_id']][(string) $row['tag']][] = self::actor($row);
            }
        }

        $stmt = $pdo->prepare(
            'SELECT threads.subject_author_identity_id AS author_identity_id, profiles.username, profiles.profile_slug
             FROM threads LEFT JOIN profiles ON profiles.identity_id = threads.subject_author_identity_id
             WHERE threads.root_post_id = :thread_id'
        );
        $stmt->execute(['thread_id' => $threadId]);
        $titleRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if (is_array($titleRow) && $titleRow['author_identity_id'] !== null) {
            $result['title_author'] = self::actor($titleRow);
        }

        return $result;
    }

    /**
     * @param list<string> $targetIds @param list<string> $tags
     * @return list<array<string, mixed>>
     */
    private static function rows(PDO $pdo, string $family, array $targetIds, array $tags, int $threshold): array
    {
        $targetPlaceholders = implode(', ', array_fill(0, count($targetIds), '?'));
        $tagPlaceholders = implode(', ', array_fill(0, count($tags), '?'));
        $stmt = $pdo->prepare(
            'SELECT viewer_tags.target_id, viewer_tags.tag, viewer_tags.author_identity_id, profiles.username, profiles.profile_slug
             FROM viewer_tags LEFT JOIN profiles ON profiles.identity_id = viewer_tags.author_identity_id
             WHERE viewer_tags.record_family = ? AND viewer_tags.eligible >= ?
               AND viewer_tags.target_id IN (' . $targetPlaceholders . ') AND viewer_tags.tag IN (' . $tagPlaceholders . ')
             ORDER BY viewer_tags.target_id, viewer_tags.tag, profiles.username, viewer_tags.author_identity_id'
        );
        $stmt->execute([$family, $threshold, ...$targetIds, ...$tags]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $rows;
    }

    /** @param array<string, mixed> $row @return array{username: string, profile_slug: ?string, identity_id: string} */
    private static function actor(array $row): array
    {
        $identityId = (string) $row['author_identity_id'];
        $username = trim((string) ($row['username'] ?? ''));

        return [
            'username' => $username !== '' ? $username : substr($identityId, -8),
            'profile_slug' => isset($row['profile_slug']) ? (string) $row['profile_slug'] : null,
            'identity_id' => $identityId,
        ];
    }
}
