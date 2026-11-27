<?php

declare(strict_types=1);

namespace ForumRewrite\View;

use ForumRewrite\Support\PledgeAmount;

/**
 * A pledge campaign as a wall of squares, one square per unit of the campaign. Read from
 * PledgeCampaign::fromPosts(), so the wall and the thread always agree.
 *
 * Each counted pledge is one block of as many squares as its whole units, in thread order;
 * squares delivered on it come first in its block. A withdrawn pledge keeps only what was
 * delivered on it, as the total does. What is left of a square (the .5 of 2.5, or all of a
 * pledge under one unit) counts in the total but is not drawn: its pledger is named under
 * the wall instead. A pledge waiting for approval follows the counted blocks, outside the
 * total. Pledges with no name or no readable number are not on the wall.
 *
 * The wall has at least the goal's squares and grows a row at a time past it.
 */
final class SupportWall
{
    public const PER_ROW = 10;

    /**
     * @param array<string, mixed> $campaign from PledgeCampaign::fromPosts()
     * @param list<array<string, mixed>> $posts the posts the campaign was read from
     * @return array{blocks: list<array{post_id: string, name: string, squares: int, delivered: int, pending: bool, post: array<string, mixed>}>, also: list<string>, drawn: int, size: int, goal: int}
     */
    public static function build(array $campaign, array $posts, int $perRow = self::PER_ROW): array
    {
        $byId = [];
        foreach ($posts as $post) {
            $byId[(string) $post['post_id']] = $post;
        }
        $counted = [];
        $pending = [];
        $also = [];
        foreach ($campaign['pledges'] as $postId => $pledge) {
            $postId = (string) $postId;
            $post = $byId[$postId] ?? null;
            if ($post === null) {
                continue;
            }
            $state = (string) $pledge['state'];
            $filled = self::hundredths($pledge['filled'] ?? null);
            $amount = match ($state) {
                PledgeCampaign::COUNTED, PledgeCampaign::WAITING => self::hundredths($pledge['amount']),
                PledgeCampaign::WITHDRAWN => $filled,
                default => 0,
            };
            if ($amount <= 0) {
                continue;
            }
            $name = trim((string) ($post['author_label'] ?? ''));
            $squares = intdiv($amount, 100);
            if ($amount % 100 !== 0 && $state !== PledgeCampaign::WAITING && !in_array($name, $also, true)) {
                $also[] = $name;
            }
            if ($squares === 0) {
                continue;
            }
            $block = [
                'post_id' => $postId,
                'name' => $name,
                'squares' => $squares,
                'delivered' => $state === PledgeCampaign::WAITING ? 0 : min($squares, intdiv($filled, 100)),
                'pending' => $state === PledgeCampaign::WAITING,
                'post' => $post,
            ];
            if ($block['pending']) {
                $pending[] = $block;
            } else {
                $counted[] = $block;
            }
        }
        $blocks = [...$counted, ...$pending];
        $drawn = array_sum(array_column($blocks, 'squares'));
        $goal = intdiv(self::hundredths($campaign['goal']), 100);
        $perRow = max(1, $perRow);
        $size = max((int) ceil($goal / $perRow), (int) ceil($drawn / $perRow)) * $perRow;

        return ['blocks' => $blocks, 'also' => $also, 'drawn' => $drawn, 'size' => $size, 'goal' => $goal];
    }

    private static function hundredths(mixed $formatted): int
    {
        return is_string($formatted) && $formatted !== '' ? (int) PledgeAmount::parse($formatted) : 0;
    }
}
