<?php

declare(strict_types=1);

namespace ForumRewrite\Inbox;

use ForumRewrite\ReadModel\RecentPostRepository;
use PDO;

/**
 * Unread posts for one Inbox section: replies to the member's posts, or other new posts.
 * The member's posts are those written by any key of their username.
 */
final class InboxFeed
{
    public const PAGE_SIZE = 50;

    /**
     * @param list<string> $identityIds every key of the member's username
     * @param string|null $claimsThread the site's backlog claims thread, whose line-only claim and
     *     release replies are bookkeeping and stay out of "other new posts"
     */
    public function __construct(
        private readonly PDO $readPdo,
        private readonly InboxMarkStore $marks,
        private readonly string $viewer,
        private readonly array $identityIds,
        private readonly ?string $claimsThread = null,
    ) {
    }

    /**
     * The oldest unread posts in a section, at most one page.
     *
     * @return list<array<string, mixed>>
     */
    public function items(string $section): array
    {
        $mark = $this->marks->mark($this->viewer, $section);
        [$filter, $params] = $this->filter($section);
        $rows = RecentPostRepository::since($this->readPdo, $mark['window_start'], $filter, $params, self::PAGE_SIZE + count($mark['acknowledged']));
        $unread = array_values(array_filter($rows, static fn (array $row): bool => !isset($mark['acknowledged'][$row['post_id']])));
        return array_slice($unread, 0, self::PAGE_SIZE);
    }

    public function unreadCount(string $section): int
    {
        $mark = $this->marks->mark($this->viewer, $section);
        [$filter, $params] = $this->filter($section);
        $late = RecentPostRepository::since($this->readPdo, $mark['window_start'], "($filter) AND posts.created_at <= ?", [...$params, $mark['mark_at']], 500);
        $lateUnread = count(array_filter($late, static fn (array $row): bool => !isset($mark['acknowledged'][$row['post_id']])));
        return RecentPostRepository::countSince($this->readPdo, $mark['mark_at'], $filter, $params) + $lateUnread;
    }

    /**
     * Acknowledges the posts a member saw, ignoring any ID that is not currently unread here.
     *
     * @param list<string> $postIds
     */
    public function catchUp(string $section, array $postIds): void
    {
        $shown = array_fill_keys(array_slice(array_values(array_filter($postIds, 'is_string')), 0, self::PAGE_SIZE), true);
        $acknowledge = [];
        foreach ($this->items($section) as $item) {
            if (isset($shown[$item['post_id']])) $acknowledge[(string) $item['post_id']] = (string) $item['created_at'];
        }
        $this->marks->catchUp($this->viewer, $section, $acknowledge);
    }

    /** @return array{0:string,1:list<string>} */
    private function filter(string $section): array
    {
        $keys = $this->identityIds === [] ? [''] : $this->identityIds;
        $in = implode(',', array_fill(0, count($keys), '?'));
        $replyToMe = "posts.parent_id IN (SELECT post_id FROM posts WHERE author_identity_id IN ($in))";
        $notMine = "COALESCE(posts.author_identity_id, '') NOT IN ($in)";
        if ($section === 'replies') {
            // A reply to the member's own post always reaches them, a release of their claim included.
            return ["$replyToMe AND $notMine", [...$keys, ...$keys]];
        }
        $other = "(posts.parent_id IS NULL OR NOT $replyToMe) AND $notMine";
        if ($this->claimsThread === null) {
            return [$other, [...$keys, ...$keys]];
        }
        // A reply in the claims thread whose whole body is one claim or release line. A claim with a
        // note, and any other reply there, still shows. The thread itself keeps every post.
        $body = "LOWER(TRIM(posts.body, ' ' || CHAR(9) || CHAR(10) || CHAR(13)))";
        $bareLine = "(posts.thread_id = ? AND posts.post_id != posts.thread_id AND INSTR($body, CHAR(10)) = 0"
            . " AND ($body LIKE 'claim:%' OR $body = 'release' OR $body LIKE 'release:%'))";

        return ["$other AND NOT $bareLine", [...$keys, ...$keys, $this->claimsThread]];
    }
}
