<?php

declare(strict_types=1);

namespace ForumRewrite\Feedback;

use ForumRewrite\Support\ClaimDirective;
use ForumRewrite\Support\ReleaseDirective;

/**
 * Who holds which backlog item, worked out from the replies in the site's
 * claims thread each time it is asked. Nothing is stored: a rebuild cannot
 * disagree with the posts.
 *
 * A claim counts when its post is unretracted and signed by an approved key
 * with counted attribution, as a pledge does. A claim with no name is a guest
 * claim: shown, never counted. A claim ends when its author or an operator
 * replies to it with a release line, or retracts it. An item that is closed or
 * blocked shows no claim. A counted claim goes stale STALE_DAYS after it was
 * made, or after the item was last touched if that is later; a stale claim is
 * still listed, but a fresh one holds the item ahead of it.
 */
final class BacklogClaims
{
    public const STALE_DAYS = 3;
    /** Workflows in which an item can no longer be started, so its claims are over. */
    public const ENDED_WORKFLOWS = ['blocked', ...FeedbackItem::CLOSED_WORKFLOWS];

    /**
     * @param array<int, array<string, mixed>> $posts the claims thread's replies, oldest first (ThreadRepository::replyPostsByThreadId)
     * @param list<FeedbackItem> $items
     * @param (callable(string): bool)|null $isOperator whether an identity is an operator now; null means nobody is
     * @return array<string, array{
     *     holder: array{name: string, identity: string, since: string, post_id: string, stale: bool}|null,
     *     also: list<array{name: string, identity: string, since: string, post_id: string, stale: bool}>,
     *     waiting: list<array{name: string, identity: string, since: string, post_id: string}>,
     *     guests: list<array{since: string, post_id: string}>
     * }> keyed by item ID; items with no claim of any kind are absent
     */
    public static function fromPosts(array $posts, array $items, int $now, ?callable $isOperator = null): array
    {
        $open = [];
        foreach ($items as $item) {
            if (!in_array($item->header('Workflow'), self::ENDED_WORKFLOWS, true)) {
                $open[$item->id()] = $item;
            }
        }
        if ($open === [] || $posts === []) {
            return [];
        }

        // Each claim post, with the items it still names.
        $claims = [];
        foreach ($posts as $post) {
            $body = (string) ($post['body'] ?? '');
            if (($post['retracted_at'] ?? null) !== null || stripos($body, 'claim') === false) {
                continue;
            }
            $ids = ClaimDirective::extract($body);
            if ($ids === null) {
                continue;
            }
            $claims[(string) $post['post_id']] = ['post' => $post, 'ids' => array_fill_keys($ids, true)];
        }
        if ($claims === []) {
            return [];
        }

        $operatorCache = [];
        $operator = static function (string $identity) use (&$operatorCache, $isOperator): bool {
            return $isOperator !== null && $identity !== '' && ($operatorCache[$identity] ??= (bool) $isOperator($identity));
        };
        foreach ($posts as $post) {
            $parent = (string) ($post['parent_id'] ?? '');
            if (!isset($claims[$parent]) || ($post['retracted_at'] ?? null) !== null) {
                continue;
            }
            $released = ReleaseDirective::extract((string) ($post['body'] ?? ''));
            $by = (string) ($post['author_identity_id'] ?? '');
            if ($released === null || $by === '' || (int) ($post['attribution_counted'] ?? 1) !== 1) {
                continue;
            }
            $own = $by === (string) ($claims[$parent]['post']['author_identity_id'] ?? '');
            if (!$own && !(self::approved($post) && $operator($by))) {
                continue;
            }
            if ($released === []) {
                $claims[$parent]['ids'] = [];
            } else {
                foreach ($released as $id) {
                    unset($claims[$parent]['ids'][$id]);
                }
            }
        }

        $result = [];
        foreach ($claims as $postId => $claim) {
            $post = $claim['post'];
            $identity = (string) ($post['author_identity_id'] ?? '');
            $since = (string) ($post['created_at'] ?? '');
            foreach (array_keys($claim['ids']) as $id) {
                if (!isset($open[$id])) {
                    continue;
                }
                $result[$id] ??= ['holder' => null, 'also' => [], 'waiting' => [], 'guests' => []];
                if ($identity === '') {
                    $result[$id]['guests'][] = ['since' => $since, 'post_id' => $postId];
                    continue;
                }
                $entry = ['name' => (string) ($post['author_label'] ?? ''), 'identity' => $identity, 'since' => $since, 'post_id' => $postId];
                if (!self::approved($post)) {
                    $result[$id]['waiting'][] = $entry;
                    continue;
                }
                $result[$id]['also'][] = $entry + ['stale' => self::stale($since, $open[$id], $now)];
            }
        }

        foreach ($result as $id => $entry) {
            // One line per person: their newest claim on the item, so claiming again refreshes a stale one.
            $byIdentity = [];
            foreach ($entry['also'] as $claim) {
                $byIdentity[$claim['identity']] = $claim;
            }
            $counted = array_values($byIdentity);
            // Fresh claims hold ahead of stale ones; within each, the earliest holds. The sort is stable.
            usort($counted, static fn (array $a, array $b): int => $a['stale'] <=> $b['stale']);
            $result[$id]['holder'] = array_shift($counted);
            $result[$id]['also'] = $counted;
        }

        return $result;
    }

    /**
     * True when a counted, unstale claim holds the item: what "Hide claimed" hides
     * and what tells a second agent to stop.
     *
     * @param array<string, mixed>|null $entry one item's entry from fromPosts()
     */
    public static function isHeld(?array $entry): bool
    {
        return $entry !== null && $entry['holder'] !== null && !$entry['holder']['stale'];
    }

    /**
     * True when this identity has a claim of its own on the item, counted or
     * waiting: nothing more for it to claim there.
     *
     * @param array<string, mixed>|null $entry
     */
    public static function hasClaimBy(?array $entry, string $identity): bool
    {
        if ($entry === null || $identity === '') {
            return false;
        }
        foreach ([...($entry['holder'] !== null ? [$entry['holder']] : []), ...$entry['also'], ...$entry['waiting']] as $claim) {
            if ($claim['identity'] === $identity) {
                return true;
            }
        }

        return false;
    }

    /**
     * The claim post a viewer may release on this item, or null: their own claim
     * first; for an operator, otherwise the holder's, then any other claim.
     *
     * @param array<string, mixed>|null $entry
     */
    public static function releasable(?array $entry, string $identity, bool $isOperator): ?string
    {
        if ($entry === null || $identity === '') {
            return null;
        }
        $claims = [...($entry['holder'] !== null ? [$entry['holder']] : []), ...$entry['also'], ...$entry['waiting']];
        foreach ($claims as $claim) {
            if ($claim['identity'] === $identity) {
                return $claim['post_id'];
            }
        }
        if (!$isOperator) {
            return null;
        }

        return $claims[0]['post_id'] ?? $entry['guests'][0]['post_id'] ?? null;
    }

    /**
     * The words a row shows for one item, in order. The start page also says
     * since when the holder has had it.
     *
     * @param array<string, mixed>|null $entry
     * @return list<string>
     */
    public static function lines(?array $entry, bool $withDate = false): array
    {
        if ($entry === null) {
            return [];
        }
        $lines = [];
        if ($entry['holder'] !== null) {
            $since = $withDate ? strtotime($entry['holder']['since']) : false;
            $lines[] = 'Claimed by ' . $entry['holder']['name'] . ($since === false ? '' : ' since ' . gmdate('M j', $since)) . ($entry['holder']['stale'] ? ' · stale' : '');
        }
        foreach ($entry['also'] as $claim) {
            $lines[] = 'Also claimed by ' . $claim['name'] . ($claim['stale'] ? ' · stale' : '');
        }
        foreach ($entry['waiting'] as $claim) {
            $lines[] = 'Claimed by ' . $claim['name'] . ' · waiting for approval';
        }
        if ($entry['guests'] !== []) {
            $lines[] = 'Claimed by a guest · not counted';
        }

        return $lines;
    }

    private static function approved(array $post): bool
    {
        return (int) ($post['author_is_approved'] ?? 0) === 1 && (int) ($post['attribution_counted'] ?? 1) === 1;
    }

    private static function stale(string $since, FeedbackItem $item, int $now): bool
    {
        $claimed = strtotime($since);
        // A touched date is a day; the item counts as touched until that day ends.
        $touched = strtotime($item->touched() . ' 23:59:59 UTC');
        $from = max($claimed === false ? 0 : $claimed, $touched === false ? 0 : $touched);

        return $now - $from > self::STALE_DAYS * 86400;
    }
}
