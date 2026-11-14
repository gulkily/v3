<?php

declare(strict_types=1);
namespace ForumRewrite\Http;

use ForumRewrite\Pagination\PageNavigation;
use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\SiteProfileRegistry;

/**
 * The guestbook: the site's guestbook thread shown as a paper book. Each
 * top-level reply is an entry; replies to an entry stay in the thread and
 * show only as a count. Pages are numbered from the oldest entry, so an old
 * page's address keeps showing the same entries. A spread holds two facing
 * pages and is addressed by its left page; no page means the newest spread.
 */
final class GuestbookPageController
{
    public const ENTRIES_PER_PAGE = 3;

    public function __construct(private readonly RouteServices $routeServices) {}

    /** The book page, or null when the requested page does not exist. */
    public function book(string $threadId, mixed $page = null): ?string
    {
        $entries = $this->entries($threadId);
        $pageCount = max(1, (int) ceil(count($entries) / self::ENTRIES_PER_PAGE));
        $lastLeft = $pageCount % 2 === 1 ? $pageCount : $pageCount - 1;
        if ($page === null || $page === '') {
            $left = $lastLeft;
        } else {
            if (!is_string($page) || preg_match('/^[1-9][0-9]{0,5}$/D', $page) !== 1) return null;
            $requested = PageNavigation::pageNumber($page);
            $left = $requested % 2 === 1 ? $requested : $requested - 1;
            if ($left > $lastLeft) return null;
        }
        $pages = [];
        foreach ([$left, $left + 1] as $number) {
            $pages[] = [
                'number' => $number,
                'entries' => array_slice($entries, ($number - 1) * self::ENTRIES_PER_PAGE, self::ENTRIES_PER_PAGE),
            ];
        }

        return $this->routeServices->renderPageTemplate('guestbook.php', [
            'guestbookThreadId' => $threadId,
            'spread' => $pages,
            'isLastSpread' => $left === $lastLeft,
            'earlierHref' => $left > 1 ? self::href($left - 2, $lastLeft) : null,
            'laterHref' => $left < $lastLeft ? self::href($left + 2, $lastLeft) : null,
            'latestHref' => $left < $lastLeft ? self::href($lastLeft, $lastLeft) : null,
        ], 'Guestbook', 'guestbook');
    }

    /** The newest spread lives at /guestbook/; every other spread at its left page number. */
    public static function href(int $left, int $lastLeft): string
    {
        return $left >= $lastLeft ? '/guestbook/' : '/guestbook/?page=' . $left;
    }

    /**
     * Top-level, unretracted replies, oldest first, each with how many replies
     * sit under it in the thread.
     *
     * @return list<array<string, mixed>>
     */
    private function entries(string $threadId): array
    {
        $pdo = $this->routeServices->pdo();
        $thread = ThreadRepository::byId($pdo, $threadId);
        if ($thread === null || ($thread['retracted_at'] ?? null) !== null) return [];
        $replies = ThreadRepository::replyPostsByThreadId($pdo, $threadId);
        $parents = [];
        foreach ($replies as $reply) $parents[(string) $reply['post_id']] = (string) ($reply['parent_id'] ?? $threadId);
        $counts = [];
        foreach ($replies as $reply) {
            if (($reply['retracted_at'] ?? null) !== null) continue;
            // Walk up to the entry this reply sits under; count it there.
            $id = (string) $reply['post_id'];
            $seen = 0;
            while (isset($parents[$id]) && $parents[$id] !== $threadId && $seen++ < 256) $id = $parents[$id];
            if ($id !== (string) $reply['post_id']) $counts[$id] = ($counts[$id] ?? 0) + 1;
        }
        $entries = [];
        foreach ($replies as $reply) {
            if (($reply['retracted_at'] ?? null) !== null) continue;
            if ((string) ($reply['parent_id'] ?? $threadId) !== $threadId) continue;
            $entries[] = $reply + ['entry_reply_count' => $counts[(string) $reply['post_id']] ?? 0];
        }

        return $entries;
    }

    /** The active site's guestbook thread, when the site has one. */
    public static function threadId(): ?string
    {
        return SiteProfileRegistry::guestbookThread();
    }
}
