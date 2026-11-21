<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

/**
 * The Zen strip at the top of the board: listed threads that carry every one
 * of the site profile's strip tags (on zenmemes.com, #zen and #meme), highest
 * thread score first (the score Top sort uses), newest first among equals, so
 * the first row is the featured meme. Each tag must come from an approved member: the root's own board tag
 * when its signed author is approved, or a label an approved member wrote.
 * The same rule as PromptLibrary's #prompt, applied to each tag. A guest's or
 * pending key's tag still shows on the thread and on tag pages; it just
 * doesn't put the thread in the strip.
 */
final class ZenStrip
{
    public const LIMIT = 12;

    /**
     * @param list<string> $tags lower-case tags, all required
     * @return list<array{root_post_id:string, root_post_created_at:string, subject:string, body_preview:string, score_total:int}>
     */
    public static function load(PDO $pdo, array $tags, int $limit = self::LIMIT): array
    {
        $tags = array_values(array_unique(array_map(static fn (string $tag): string => strtolower($tag), $tags)));
        if ($tags === [] || $limit < 1) {
            return [];
        }
        $board = ThreadKeysetPage::listJson('threads.board_tags_json');
        $labels = ThreadKeysetPage::listJson('threads.thread_labels_json');
        $authorIsApproved = '(SELECT CASE WHEN profiles.retired_at IS NOT NULL THEN profiles.was_approved ELSE profiles.is_approved END FROM profiles WHERE profiles.identity_id = posts.author_identity_id)';
        $authorApproved = 'posts.attribution_counted = 1 AND COALESCE(' . $authorIsApproved . ', 0) = 1';
        $conditions = [];
        $params = [];
        foreach ($tags as $index => $tag) {
            $param = 'tag' . $index;
            $params[$param] = $tag;
            // viewer_tags keeps retracted label records, so the label must still be on the thread.
            $conditions[] = "((EXISTS (SELECT 1 FROM json_each($board) WHERE type = 'text' AND lower(value) = :$param) AND $authorApproved)
                OR (EXISTS (SELECT 1 FROM json_each($labels) WHERE type = 'text' AND lower(value) = :$param)
                    AND EXISTS (SELECT 1 FROM viewer_tags JOIN profiles AS labelers ON labelers.identity_id = viewer_tags.author_identity_id
                        WHERE viewer_tags.record_family = 'thread-labels' AND viewer_tags.target_id = threads.root_post_id
                          AND viewer_tags.tag = :$param AND labelers.is_approved = 1 AND labelers.retired_at IS NULL)))";
        }
        $statement = $pdo->prepare('SELECT threads.root_post_id, threads.root_post_created_at, threads.subject, threads.body_preview, threads.score_total'
            . ThreadKeysetPage::listedRootsSql()
            . ' AND ' . implode(' AND ', $conditions)
            . ' ORDER BY threads.score_total DESC, threads.root_post_created_at DESC, threads.root_post_id DESC LIMIT ' . $limit);
        $statement->execute($params);

        return array_map(static fn (array $row): array => [
            'root_post_id' => (string) $row['root_post_id'],
            'root_post_created_at' => (string) $row['root_post_created_at'],
            'subject' => (string) $row['subject'],
            'body_preview' => (string) $row['body_preview'],
            'score_total' => (int) $row['score_total'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
