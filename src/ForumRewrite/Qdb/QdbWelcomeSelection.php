<?php

declare(strict_types=1);

namespace ForumRewrite\Qdb;

use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\ReadModel\ThreadRowSupport;
use PDO;

/** Count eligible quotes and select welcome rows without loading unselected bodies. */
final class QdbWelcomeSelection
{
    /** @return array{qdbQuoteCount:int,recentThreads:array,newsThreads:array,hasMoreNews:bool} */
    public static function read(PDO $pdo, QdbBoardPolicy $policy): array
    {
        $statement = $pdo->query('SELECT threads.root_post_id, threads.root_post_created_at, threads.board_tags_json
            FROM threads JOIN posts ON posts.post_id = threads.root_post_id
            WHERE threads.retracted_at IS NULL
            ORDER BY threads.last_activity_at DESC, threads.root_post_id DESC');
        $quoteCount = $newsCount = 0;
        $recent = $news = [];
        try {
            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (ThreadRowSupport::isHiddenBootstrapBoardTagsJson((string) $row['board_tags_json'])) {
                    continue;
                }
                if (QdbQuoteNumbers::fromThreadId((string) $row['root_post_id']) !== null) {
                    $quoteCount++;
                    if (count($recent) < 5) $recent[] = $row;
                }
                $row['board_tags'] = ThreadRowSupport::decodeStringList((string) $row['board_tags_json']);
                if (in_array('news', $row['board_tags'], true)) {
                    $newsCount++;
                    // Reuse the authored-date/ID ordering, keeping at most four metadata rows.
                    $news = $policy->newsThreads([...$news, $row]);
                    if (count($news) > 3) array_pop($news);
                }
            }
        } finally {
            $statement->closeCursor();
        }
        $loaded = [];
        $hydrate = static function (array $rows) use ($pdo, &$loaded): array {
            $result = [];
            foreach ($rows as $row) {
                $id = (string) $row['root_post_id'];
                if (!array_key_exists($id, $loaded)) $loaded[$id] = ThreadRepository::byId($pdo, $id);
                if ($loaded[$id] !== null) $result[] = $loaded[$id];
            }
            return $result;
        };
        return [
            'qdbQuoteCount' => $quoteCount,
            'recentThreads' => $hydrate($recent),
            'newsThreads' => $hydrate($news),
            'hasMoreNews' => $newsCount > 3,
        ];
    }
}
