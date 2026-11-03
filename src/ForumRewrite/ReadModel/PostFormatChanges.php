<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Canonical\PostFormatRecord;
use ForumRewrite\Support\PostText;
use PDO;

/**
 * Applies post-format records to the read model. Both the full rebuild and incremental
 * updates use this, so they cannot disagree: the latest record per post (by Created-At,
 * then Record-ID) sets posts.body_format, and a thread root's preview is recomputed.
 * Retracted posts are left alone.
 */
final class PostFormatChanges
{
    /**
     * @param list<PostFormatRecord> $records
     * @return array<string, string> post ID => format
     */
    public static function latestByPost(array $records): array
    {
        usort($records, static fn (PostFormatRecord $a, PostFormatRecord $b): int => [$a->createdAt, $a->recordId] <=> [$b->createdAt, $b->recordId]);
        $latest = [];
        foreach ($records as $record) {
            $latest[$record->postId] = $record->bodyFormat;
        }

        return $latest;
    }

    /** @param array<string, string> $formatByPost */
    public static function apply(PDO $pdo, array $formatByPost): void
    {
        if ($formatByPost === []) {
            return;
        }
        $select = $pdo->prepare('SELECT post_id, thread_id, body, retracted_at FROM posts WHERE post_id = :post_id');
        $updatePost = $pdo->prepare('UPDATE posts SET body_format = :body_format WHERE post_id = :post_id');
        $updatePreview = $pdo->prepare('UPDATE threads SET body_preview = :body_preview WHERE root_post_id = :post_id AND retracted_at IS NULL');
        foreach ($formatByPost as $postId => $format) {
            $select->execute(['post_id' => $postId]);
            $row = $select->fetch(PDO::FETCH_ASSOC);
            if ($row === false || $row['retracted_at'] !== null) {
                continue;
            }
            $updatePost->execute(['body_format' => $format, 'post_id' => $postId]);
            if ($row['thread_id'] === $postId) {
                $updatePreview->execute(['body_preview' => self::preview(PostText::plainPreview((string) $row['body'], $format)), 'post_id' => $postId]);
            }
        }
    }

    /** Same first-line rule as the builder's and updater's previews. */
    private static function preview(string $body): string
    {
        $line = strtok($body, "\n");

        return $line === false ? '' : $line;
    }
}
