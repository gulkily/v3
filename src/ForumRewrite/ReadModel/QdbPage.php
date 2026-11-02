<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Qdb\QdbQuoteNumbers;
use PDO;

/** Numbered QDB selection: count and choose IDs before hydrating bodies. */
final class QdbPage
{
    public const SIZE = 25;

    /** @return array{threads:array,count:int,page:int} */
    public static function page(PDO $pdo, string $section, int $page, string $term = ''): array
    {
        if (!in_array($section, ['latest', 'top', 'leetness', 'search'], true)) {
            throw new \InvalidArgumentException('Unknown QDB page section.');
        }
        // Reuse PHP policies, including malformed legacy lists and exact ID suffixes.
        $pdo->sqliteCreateFunction('qdb_page_eligible', static fn ($id, $tags): bool =>
            QdbQuoteNumbers::fromThreadId((string) $id) !== null
            && !ThreadRowSupport::isHiddenBootstrapBoardTagsJson((string) $tags), 2);
        $pdo->sqliteCreateFunction('qdb_page_pinned', static fn ($labels): bool =>
            in_array('pinned', ThreadRowSupport::decodeStringList((string) $labels), true), 1);
        $base = ' FROM threads JOIN posts ON posts.post_id = threads.root_post_id
            WHERE threads.retracted_at IS NULL
            AND qdb_page_eligible(threads.root_post_id, threads.board_tags_json)';
        $scoreOrder = match ($section) {
            'top' => 'CAST(threads.score_total AS INTEGER) DESC, ',
            'leetness' => 'ABS(1337 - CAST(threads.score_total AS INTEGER)) ASC, ',
            default => '',
        };
        $order = 'qdb_page_pinned(threads.thread_labels_json) DESC, ' . $scoreOrder . '
            threads.root_post_created_at COLLATE BINARY DESC, threads.root_post_id COLLATE BINARY DESC';
        if ($section === 'search') {
            $term = trim($term);
            $pdo->sqliteCreateFunction('qdb_page_matches', static fn ($body): bool =>
                stripos((string) $body, $term) !== false, 1);
            $base .= $term === '' ? ' AND 0' : ' AND qdb_page_matches(posts.body)';
            $order = 'threads.last_activity_at COLLATE BINARY DESC, threads.root_post_id COLLATE BINARY DESC';
        }
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $countStatement = $pdo->query('SELECT COUNT(*)' . $base);
            $count = (int) $countStatement->fetchColumn();
            $countStatement->closeCursor();
            $page = max(1, min($page, max(1, (int) ceil($count / self::SIZE))));
            $statement = $pdo->query('SELECT threads.root_post_id' . $base . ' ORDER BY ' . $order
                . ' LIMIT ' . self::SIZE . ' OFFSET ' . (($page - 1) * self::SIZE));
            $ids = $statement->fetchAll(PDO::FETCH_COLUMN);
            $statement->closeCursor();
            $threads = [];
            foreach ($ids as $id) {
                $threads[] = ThreadRepository::byId($pdo, $id);
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return ['threads' => $threads, 'count' => $count, 'page' => $page];
        } catch (\Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }
}
