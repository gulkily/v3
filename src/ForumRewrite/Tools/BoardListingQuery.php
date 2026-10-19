<?php
declare(strict_types=1);

namespace ForumRewrite\Tools;

use ForumRewrite\Http\BoardViewOptions;
use ForumRewrite\ReadModel\ThreadRepository;

final class BoardListingQuery
{
    /** @return array{queryKey:string,queryLabel:string,querySql:string} */
    public static function context(string $view, string $sort): array
    {
        $view = BoardViewOptions::normalizeView($view);
        $sort = BoardViewOptions::normalizeSort($sort);
        $order = match ($sort) {
            'oldest' => 'root_post_created_at ASC, root_post_id ASC',
            'top' => 'score_total DESC, root_post_created_at DESC, root_post_id DESC',
            default => 'root_post_created_at DESC, root_post_id DESC',
        };
        $filter = $view === 'liked' ? 'AND ' . self::hasLabel('thread_labels_json', 'like') . " AND root_post_score_total >= 0\n" : '';
        $sql = "SELECT * FROM (\n" . ThreadRepository::boardRowsSql() . "\n) AS board_rows\n"
            . 'WHERE NOT ' . self::hasLabel('board_tags_json', 'identity') . "\n"
            . $filter
            . 'ORDER BY CASE WHEN ' . self::hasLabel('thread_labels_json', 'pinned') . ' THEN 0 ELSE 1 END, ' . $order;
        return ['queryKey' => 'board-' . $view . '-' . $sort, 'queryLabel' => ucfirst($view) . ' / ' . $sort, 'querySql' => $sql];
    }

    private static function hasLabel(string $column, string $label): string
    {
        // Match ThreadRowSupport: malformed/scalar JSON has no list labels.
        $list = "CASE WHEN json_valid($column) THEN CASE WHEN json_type($column) IN ('array', 'object') THEN $column ELSE '[]' END ELSE '[]' END";
        return "EXISTS (SELECT 1 FROM json_each($list) WHERE type = 'text' AND value = '$label')";
    }
}
