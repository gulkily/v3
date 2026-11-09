<?php

declare(strict_types=1);

namespace ForumRewrite\Write;

use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use PDO;
use PDOException;

/**
 * Decides, before a removal is committed, whether the read model can be updated for that one
 * post afterwards: it must be current at HEAD and contain the post. Shared by operator
 * deletions (`delete-record`) and signed retractions so both decide the same way.
 */
final class RemovalUpdateContext
{
    /**
     * @return array{thread_id: string, tags: list<string>, profile_slug: ?string}|null null when
     *         only a full rebuild is safe
     */
    public static function forPost(string $root, string $database, string $id): ?array
    {
        if (!is_file($database) || (new ReadModelStaleMarker($database))->exists()) return null;
        try {
            $pdo = new PDO('sqlite:' . $database, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $metadata = $pdo->query('SELECT key, value FROM metadata')->fetchAll(PDO::FETCH_KEY_PAIR);
            if (!ReadModelMetadata::hasExpectedSchemaIdentity($metadata)
                || ($metadata['repository_head'] ?? null) !== ReadModelMetadata::repositoryHead($root)) return null;
            $post = $pdo->prepare('SELECT thread_id, author_profile_slug FROM posts WHERE post_id = ?');
            $post->execute([$id]);
            $row = $post->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) return null;
            $thread = $pdo->prepare('SELECT board_tags_json, thread_labels_json FROM threads WHERE root_post_id = ?');
            $thread->execute([$row['thread_id']]);
            $tags = [];
            foreach ((array) $thread->fetch(PDO::FETCH_ASSOC) as $json) {
                $decoded = json_decode((string) $json, true);
                if (is_array($decoded)) $tags = array_merge($tags, array_filter($decoded, 'is_string'));
            }
            return ['thread_id' => (string) $row['thread_id'], 'tags' => array_values(array_unique($tags)), 'profile_slug' => $row['author_profile_slug'] !== null ? (string) $row['author_profile_slug'] : null];
        } catch (PDOException) {
            return null;
        }
    }
}
