<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

/**
 * Select membership and page numbers before loading any thread bodies. A tag
 * page can name several tags (/tags/zen+meme); a thread must carry all of them,
 * each as a board tag or a label.
 */
final class TagPage
{
    /** @return array{items:array,has_next:bool,exists:bool} */
    /** @param string|list<string> $tag */
    public static function page(PDO $pdo, string|array $tag, int $page = 1): array
    {
        [$base, $params] = self::base($tag);
        $exists = $pdo->prepare('SELECT 1' . $base . ' LIMIT 1');
        $exists->execute($params);
        $known = $exists->fetchColumn() !== false;
        $exists->closeCursor();

        return ThreadKeysetPage::select($pdo, $base, $params, $page) + ['exists' => $known];
    }

    /** @param string|list<string> $tag */
    public static function pageOf(PDO $pdo, string|array $tag, string $threadId): ?int
    {
        [$base, $params] = self::base($tag);
        return ThreadKeysetPage::pageOf($pdo, $base, $params, $threadId);
    }

    /**
     * @param string|list<string> $tags
     * @return array{0:string,1:array<string,string>}
     */
    private static function base(string|array $tags): array
    {
        $board = ThreadKeysetPage::listJson('threads.board_tags_json');
        $labels = ThreadKeysetPage::listJson('threads.thread_labels_json');
        $sql = ThreadKeysetPage::listedRootsSql();
        $params = [];
        foreach (array_values(array_unique(array_map('strtolower', (array) $tags))) as $index => $tag) {
            $param = $index === 0 ? 'tag' : 'tag' . $index;
            $params[$param] = $tag;
            $sql .= "
            AND (EXISTS (SELECT 1 FROM json_each($board) WHERE type = 'text' AND lower(value) = :$param)
              OR EXISTS (SELECT 1 FROM json_each($labels) WHERE type = 'text' AND lower(value) = :$param))";
        }
        return [$sql, $params];
    }
}
