<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use InvalidArgumentException;
use PDO;

/** Select membership and stable positions before loading any thread bodies. */
final class TagPage
{
    public const SIZE = 25;

    public static function context(string $tag): string
    {
        return 'tag:' . strtolower($tag) . ':activity:desc';
    }

    /** @return array{items:array,previous:?array,next:?array,exists:bool} */
    public static function page(PDO $pdo, string $tag, ?array $boundary = null, string $direction = 'next'): array
    {
        if (!in_array($direction, ['next', 'previous'], true)
            || ($boundary !== null && (array_keys($boundary) !== ['activity', 'id']
                || !is_string($boundary['activity']) || !is_string($boundary['id'])
                || $boundary['activity'] === '' || $boundary['id'] === ''
                || strlen($boundary['activity']) > 128 || strlen($boundary['id']) > 512))) {
            throw new InvalidArgumentException('This page position is invalid.');
        }
        $tag = strtolower($tag);
        $board = self::listJson('threads.board_tags_json');
        $labels = self::listJson('threads.thread_labels_json');
        $base = " FROM threads JOIN posts ON posts.post_id = threads.root_post_id
            WHERE threads.retracted_at IS NULL
            AND NOT EXISTS (SELECT 1 FROM json_each($board) WHERE type = 'text' AND value = 'identity' COLLATE BINARY)
            AND (EXISTS (SELECT 1 FROM json_each($board) WHERE type = 'text' AND lower(value) = :tag)
              OR EXISTS (SELECT 1 FROM json_each($labels) WHERE type = 'text' AND lower(value) = :tag))";
        $exists = $pdo->prepare('SELECT 1' . $base . ' LIMIT 1');
        $exists->execute(['tag' => $tag]);
        $known = $exists->fetchColumn() !== false;
        $exists->closeCursor();
        $params = ['tag' => $tag];
        $condition = $boundary === null ? '' : self::boundarySql($direction === 'next' ? '<' : '>');
        if ($boundary !== null) $params += $boundary;
        $order = $direction === 'previous' ? 'ASC' : 'DESC';
        $statement = $pdo->prepare('SELECT threads.root_post_id, threads.last_activity_at' . $base . $condition
            . " ORDER BY threads.last_activity_at $order, threads.root_post_id $order LIMIT " . (self::SIZE + 1));
        $statement->execute($params);
        $rows = array_slice($statement->fetchAll(PDO::FETCH_ASSOC), 0, self::SIZE);
        $statement->closeCursor();
        if ($direction === 'previous') $rows = array_reverse($rows);
        $previous = $next = null;
        if ($rows !== []) {
            $first = self::boundary($rows[0]);
            $last = self::boundary($rows[count($rows) - 1]);
            foreach ([['>', $first, 'previous'], ['<', $last, 'next']] as [$operator, $edge, $which]) {
                $probe = $pdo->prepare('SELECT 1' . $base . self::boundarySql($operator) . ' LIMIT 1');
                $probe->execute(['tag' => $tag] + $edge);
                if ($probe->fetchColumn() !== false) {
                    if ($which === 'previous') $previous = $edge; else $next = $edge;
                }
                $probe->closeCursor();
            }
        }
        $items = [];
        foreach ($rows as $row) {
            $thread = ThreadRepository::byId($pdo, (string) $row['root_post_id']);
            if ($thread !== null) $items[] = $thread;
        }
        return ['items' => $items, 'previous' => $previous, 'next' => $next, 'exists' => $known];
    }

    private static function listJson(string $column): string
    {
        return "CASE WHEN json_valid($column) THEN CASE WHEN json_type($column) IN ('array','object') THEN $column ELSE '[]' END ELSE '[]' END";
    }

    private static function boundarySql(string $operator): string
    {
        return " AND (threads.last_activity_at $operator :activity OR (threads.last_activity_at = :activity AND threads.root_post_id $operator :id))";
    }

    private static function boundary(array $row): array
    {
        return ['activity' => (string) $row['last_activity_at'], 'id' => (string) $row['root_post_id']];
    }
}
