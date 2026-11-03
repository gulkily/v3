<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

/** Select membership and stable positions before loading any thread bodies. */
final class TagPage
{
    public const SIZE = ThreadKeysetPage::SIZE;

    public static function context(string $tag): string
    {
        return 'tag:' . strtolower($tag) . ':activity:desc';
    }

    /** @return array{items:array,previous:?array,next:?array,exists:bool} */
    public static function page(PDO $pdo, string $tag, ?array $boundary = null, string $direction = 'next'): array
    {
        ThreadKeysetPage::validate($boundary, $direction);
        $tag = strtolower($tag);
        $board = ThreadKeysetPage::listJson('threads.board_tags_json');
        $labels = ThreadKeysetPage::listJson('threads.thread_labels_json');
        $base = ThreadKeysetPage::listedRootsSql() . "
            AND (EXISTS (SELECT 1 FROM json_each($board) WHERE type = 'text' AND lower(value) = :tag)
              OR EXISTS (SELECT 1 FROM json_each($labels) WHERE type = 'text' AND lower(value) = :tag))";
        $exists = $pdo->prepare('SELECT 1' . $base . ' LIMIT 1');
        $exists->execute(['tag' => $tag]);
        $known = $exists->fetchColumn() !== false;
        $exists->closeCursor();

        return ThreadKeysetPage::select($pdo, $base, ['tag' => $tag], $boundary, $direction) + ['exists' => $known];
    }
}
