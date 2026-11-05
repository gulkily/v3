<?php

declare(strict_types=1);

namespace ForumRewrite\Images;

use PDO;

/**
 * Small thumbnails for list rows (board and Recent): the post's attached
 * image, looked up for a whole page in one query by post ID. A post without a
 * well-formed reference, a retracted post or a missing file gets none.
 */
final class ListThumbnails
{
    /**
     * @param list<string> $postIds
     * @return array<string, array{src:string, width:int, height:int}>
     */
    public static function forPosts(PDO $pdo, ImageStore $store, array $postIds): array
    {
        $postIds = array_values(array_unique(array_filter($postIds, 'is_string')));
        if ($postIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $query = $pdo->prepare('SELECT post_id, body FROM posts WHERE post_id IN (' . $placeholders . ')
            AND retracted_at IS NULL AND instr(body, ?) > 0');
        $query->execute([...$postIds, ImageReference::PREFIX]);
        $thumbnails = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            try {
                $reference = ImageReference::fromBody((string) $row['body']);
                if ($reference === null || !$store->isPresent($reference)) {
                    continue;
                }
            } catch (\RuntimeException) {
                continue;
            }
            $thumbnails[(string) $row['post_id']] = [
                'src' => '/images/' . ImageReference::filename($reference),
                'width' => $reference['width'],
                'height' => $reference['height'],
            ];
        }
        return $thumbnails;
    }

    /**
     * Adds a `thumbnail` entry to each row that has one.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public static function attach(PDO $pdo, string $repositoryRoot, bool $imagesEnabled, array $rows, string $idKey): array
    {
        if (!$imagesEnabled || $rows === []) {
            return $rows;
        }
        try {
            $thumbnails = self::forPosts($pdo, new ImageStore($repositoryRoot), array_map(static fn (array $row): string => (string) ($row[$idKey] ?? ''), $rows));
        } catch (\RuntimeException) {
            // A misconfigured image root leaves lists as they were.
            return $rows;
        }
        foreach ($rows as &$row) {
            $id = (string) ($row[$idKey] ?? '');
            if (isset($thumbnails[$id])) {
                $row['thumbnail'] = $thumbnails[$id];
            }
        }
        unset($row);
        return $rows;
    }
}
