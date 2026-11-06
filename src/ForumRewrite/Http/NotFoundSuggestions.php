<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\TagGrouping;
use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\ReadModel\ThreadRowSupport;
use PDO;

/**
 * Things to read next on a 404 page: a few recently active threads and the
 * labels most used among recent threads. 404s are mostly bots and scanners,
 * so this reads one bounded window off threads_last_activity_idx rather
 * than the whole threads table the way /tags/ does.
 */
final class NotFoundSuggestions
{
    private const WINDOW = 100;
    private const THREAD_LIMIT = 5;
    private const LABEL_LIMIT = 10;
    /** Present on most threads, so it doesn't help anyone find something. */
    private const SKIPPED_LABELS = ['general'];

    /**
     * @return array{threads: array<int, array<string, mixed>>, labels: array<int, array{tag: string, href: string, count: int}>}
     */
    public static function fromReadModel(PDO $pdo): array
    {
        $statement = $pdo->query(ThreadRepository::boardRowsSql() . ' LIMIT ' . self::WINDOW);
        $threads = [];
        try {
            while (($row = $statement->fetch()) !== false) {
                if (!ThreadRowSupport::isHiddenBootstrapBoardTagsJson((string) $row['board_tags_json'])) {
                    $thread = ThreadRowSupport::hydrateThreadRow($row);
                    unset($thread['root_post_body']);
                    $threads[] = $thread;
                }
            }
        } finally {
            $statement->closeCursor();
        }

        $labels = [];
        foreach (TagGrouping::previewsFromUniqueThreads($threads, 0) as $group) {
            if (in_array($group['tag'], self::SKIPPED_LABELS, true)) {
                continue;
            }
            $labels[] = ['tag' => $group['tag'], 'href' => $group['href'], 'count' => (int) $group['count']];
            if (count($labels) === self::LABEL_LIMIT) {
                break;
            }
        }

        return ['threads' => array_slice($threads, 0, self::THREAD_LIMIT), 'labels' => $labels];
    }

    /**
     * Compose link for reporting the dead address as a #meta #bug thread.
     * The visitor sees and edits all of it before anything is posted.
     */
    public static function reportUrl(string $requestPath, string $referrer): string
    {
        $path = mb_substr($requestPath, 0, 200);
        if ($path === '' || $path[0] !== '/') {
            return '';
        }
        $body = 'This address gave me a 404: ' . $path;
        if (preg_match('#^https?://#i', $referrer) === 1) {
            $body .= "\n\nI got there from: " . mb_substr($referrer, 0, 300);
        }

        return '/compose/thread?' . http_build_query([
            'subject' => 'Broken link: ' . $path . ' #meta #bug',
            'body' => $body,
        ]);
    }
}
