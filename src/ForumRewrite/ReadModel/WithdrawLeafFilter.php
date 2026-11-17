<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Support\PledgeLines;
use ForumRewrite\Support\ReviewDirective;

/**
 * Takes a pledge withdrawal or fill out of the conversation when the reply is its `withdraw`
 * or `filled` line and nothing else (an amount included, as in `filled: 5`), took effect and
 * no shown reply answers it: the pledge already says
 * "Withdrawn", "Delivered by" or "Filled by" and links to it. A withdrawal with a note, one that someone answered, and one
 * that did not take effect (its reason has to be seen) all stay. A bare `confirm` that counted folds the same
 * way, since the fill line says "confirmed by"; a dispute never folds, so the pledger can answer it.
 * Repeats until stable.
 * Display only, like TagOnlyLeafFilter: the reply and its own page remain. This is a third
 * leaf filter because the rule needs the campaign's reading of each reply.
 */
final class WithdrawLeafFilter
{
    /**
     * @param list<array<string,mixed>> $replies replies only, root excluded, in thread order
     * @param array<string, array{effective: bool, note: string}> $withdrawals from PledgeCampaign::fromPosts()
     * @param array<string, array{effective: bool, note: string}> $fills from PledgeCampaign::fromPosts()
     * @param array<string, array{effective: bool, note: string}> $reviews from PledgeCampaign::fromPosts()
     * @return list<array<string,mixed>> the replies still shown, in thread order
     */
    public static function apply(array $replies, array $withdrawals, array $fills = [], array $reviews = []): array
    {
        if ($withdrawals === [] && $fills === [] && $reviews === []) {
            return $replies;
        }
        $foldedIds = [];
        do {
            $answered = [];
            foreach ($replies as $reply) {
                if (!isset($foldedIds[(string) $reply['post_id']]) && (string) ($reply['parent_id'] ?? '') !== '') {
                    $answered[(string) $reply['parent_id']] = true;
                }
            }
            $changed = false;
            foreach ($replies as $reply) {
                $id = (string) $reply['post_id'];
                $body = (string) ($reply['body'] ?? '');
                // A fill counted only in part keeps its card, since its note ("Counted 300 of 500") has to be seen.
                $bare = (($withdrawals[$id]['effective'] ?? false) && PledgeLines::isOnlyLine($body, PledgeLines::WITHDRAW))
                    || (($fills[$id]['effective'] ?? false) && ($fills[$id]['note'] ?? '') === '' && PledgeLines::isOnlyLine($body, PledgeLines::FILLED))
                    || (($reviews[$id]['effective'] ?? false) && PledgeLines::isOnlyLine($body, PledgeLines::REVIEW)
                        && (ReviewDirective::extract($body)['word'] ?? '') === ReviewDirective::CONFIRM);
                if (!isset($foldedIds[$id]) && !isset($answered[$id]) && $bare) {
                    $foldedIds[$id] = true;
                    $changed = true;
                }
            }
        } while ($changed);

        return array_values(array_filter($replies, static fn (array $reply): bool => !isset($foldedIds[(string) $reply['post_id']])));
    }
}
