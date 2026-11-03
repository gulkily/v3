<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use InvalidArgumentException;
use PDO;

/**
 * Bounded pages of listed threads by latest activity, newest first, for the
 * shared Previous/Next navigation. Callers add their own membership condition
 * to the board's eligibility; only the selected roots are hydrated.
 */
final class ThreadKeysetPage
{
    public const SIZE = 25;

    /** Listed roots: not retracted and not identity bootstrap threads, as on the board. */
    public static function listedRootsSql(): string
    {
        $board = self::listJson('threads.board_tags_json');

        return " FROM threads JOIN posts ON posts.post_id = threads.root_post_id
            WHERE threads.retracted_at IS NULL
            AND NOT EXISTS (SELECT 1 FROM json_each($board) WHERE type = 'text' AND value = 'identity' COLLATE BINARY)";
    }

    /** A JSON list column as an iterable value; malformed or scalar values iterate nothing. */
    public static function listJson(string $column): string
    {
        return "CASE WHEN json_valid($column) THEN CASE WHEN json_type($column) IN ('array','object') THEN $column ELSE '[]' END ELSE '[]' END";
    }

    /** @param array<string, string>|null $boundary */
    public static function validate(?array $boundary, string $direction): void
    {
        if (!in_array($direction, ['next', 'previous'], true)
            || ($boundary !== null && (array_keys($boundary) !== ['activity', 'id']
                || !is_string($boundary['activity']) || !is_string($boundary['id'])
                || $boundary['activity'] === '' || $boundary['id'] === ''
                || strlen($boundary['activity']) > 128 || strlen($boundary['id']) > 512))) {
            throw new InvalidArgumentException('This page position is invalid.');
        }
    }

    /**
     * @param string $base FROM/WHERE clause starting with listedRootsSql().
     * @param array<string, string> $params Named parameters used by $base.
     * @param array<string, string>|null $boundary
     * @return array{items:array,previous:?array,next:?array}
     */
    public static function select(PDO $pdo, string $base, array $params, ?array $boundary, string $direction): array
    {
        self::validate($boundary, $direction);
        $condition = $boundary === null ? '' : self::boundarySql($direction === 'next' ? '<' : '>');
        $order = $direction === 'previous' ? 'ASC' : 'DESC';
        $statement = $pdo->prepare('SELECT threads.root_post_id, threads.last_activity_at' . $base . $condition
            . " ORDER BY threads.last_activity_at $order, threads.root_post_id $order LIMIT " . (self::SIZE + 1));
        $statement->execute($params + ($boundary ?? []));
        $rows = array_slice($statement->fetchAll(PDO::FETCH_ASSOC), 0, self::SIZE);
        $statement->closeCursor();
        if ($direction === 'previous') $rows = array_reverse($rows);
        $previous = $next = null;
        if ($rows !== []) {
            $first = self::boundary($rows[0]);
            $last = self::boundary($rows[count($rows) - 1]);
            foreach ([['>', $first, 'previous'], ['<', $last, 'next']] as [$operator, $edge, $which]) {
                $probe = $pdo->prepare('SELECT 1' . $base . self::boundarySql($operator) . ' LIMIT 1');
                $probe->execute($params + $edge);
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
        return ['items' => $items, 'previous' => $previous, 'next' => $next];
    }

    private static function boundarySql(string $operator): string
    {
        return " AND (threads.last_activity_at $operator :activity OR (threads.last_activity_at = :activity AND threads.root_post_id $operator :id))";
    }

    /** @return array{activity:string,id:string} */
    private static function boundary(array $row): array
    {
        return ['activity' => (string) $row['last_activity_at'], 'id' => (string) $row['root_post_id']];
    }
}
