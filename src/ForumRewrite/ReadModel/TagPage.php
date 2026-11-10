<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

/** Select membership and page numbers before loading any thread bodies. */
final class TagPage
{
    /** @return array{items:array,has_next:bool,exists:bool} */
    public static function page(PDO $pdo, string $tag, int $page = 1): array
    {
        [$base, $params] = self::base($tag);
        $exists = $pdo->prepare('SELECT 1' . $base . ' LIMIT 1');
        $exists->execute($params);
        $known = $exists->fetchColumn() !== false;
        $exists->closeCursor();

        return ThreadKeysetPage::select($pdo, $base, $params, $page) + ['exists' => $known];
    }

    public static function pageOf(PDO $pdo, string $tag, string $threadId): ?int
    {
        [$base, $params] = self::base($tag);
        return ThreadKeysetPage::pageOf($pdo, $base, $params, $threadId);
    }

    /** @return array{0:string,1:array<string,string>} */
    private static function base(string $tag): array
    {
        $board = ThreadKeysetPage::listJson('threads.board_tags_json');
        $labels = ThreadKeysetPage::listJson('threads.thread_labels_json');
        return [ThreadKeysetPage::listedRootsSql() . "
            AND (EXISTS (SELECT 1 FROM json_each($board) WHERE type = 'text' AND lower(value) = :tag)
              OR EXISTS (SELECT 1 FROM json_each($labels) WHERE type = 'text' AND lower(value) = :tag))", ['tag' => strtolower($tag)]];
    }
}
