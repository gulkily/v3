<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

/**
 * Bounded numbered pages of listed threads by latest activity, newest first,
 * for the shared Previous/Next navigation. Callers add their own membership condition
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

    /**
     * One numbered page, newest activity first, with thread ID breaking ties.
     *
     * @param string $base FROM/WHERE clause starting with listedRootsSql().
     * @param array<string, string> $params Named parameters used by $base.
     * @return array{items:array,has_next:bool}
     */
    public static function select(PDO $pdo, string $base, array $params, int $page = 1): array
    {
        $offset = (max(1, $page) - 1) * self::SIZE;
        $statement = $pdo->prepare('SELECT threads.root_post_id' . $base
            . ' ORDER BY threads.last_activity_at DESC, threads.root_post_id DESC LIMIT ' . (self::SIZE + 1) . ' OFFSET ' . $offset);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        $hasNext = count($rows) > self::SIZE;
        $items = [];
        foreach (array_slice($rows, 0, self::SIZE) as $row) {
            $thread = ThreadRepository::byId($pdo, (string) $row['root_post_id']);
            if ($thread !== null) $items[] = $thread;
        }
        return ['items' => $items, 'has_next' => $hasNext];
    }

    /** The page holding a listed thread, or null when it is not in this list. */
    public static function pageOf(PDO $pdo, string $base, array $params, string $threadId): ?int
    {
        $target = $pdo->prepare('SELECT threads.last_activity_at' . $base . ' AND threads.root_post_id = :with_id');
        $target->execute($params + ['with_id' => $threadId]);
        $activity = $target->fetchColumn();
        $target->closeCursor();
        if ($activity === false) return null;
        $before = $pdo->prepare('SELECT count(*)' . $base
            . ' AND (threads.last_activity_at > :with_activity OR (threads.last_activity_at = :with_activity AND threads.root_post_id > :with_id))');
        $before->execute($params + ['with_activity' => (string) $activity, 'with_id' => $threadId]);
        $count = (int) $before->fetchColumn();
        $before->closeCursor();
        return intdiv($count, self::SIZE) + 1;
    }
}
