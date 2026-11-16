<?php

declare(strict_types=1);

namespace ForumRewrite\View;

use ForumRewrite\Support\GoalDirective;
use ForumRewrite\Support\PledgeAmount;
use ForumRewrite\Support\PledgeDirective;

/**
 * A thread's pledge campaign, derived when read from the posts the thread page already
 * loads: the `goal:` line in the opening post, and the `pledge:` line in each reply.
 * Nothing is stored, so a rebuild cannot disagree with the posts.
 *
 * A pledge counts when its post is unretracted, names its author, has counted
 * attribution (so an unverified post counts only while Verified attribution is off),
 * and reads as a number, optionally followed by the campaign's unit. An instance can
 * choose to count posts with no name too (FORUM_PLEDGES_COUNT_UNNAMED_ENABLED). Every other
 * pledge line stays visible with the reason it is not counted.
 */
final class PledgeCampaign
{
    public const COUNTED = 'counted';
    public const UNVERIFIED = 'unverified';
    public const UNNAMED = 'unnamed';
    public const UNREADABLE = 'unreadable';

    /**
     * @param array<int, array<string, mixed>> $posts thread rows, root included
     * @param bool $countUnnamed count pledges whose post names no author
     * @return array{
     *     goal: string, unit: string, pledged: string, count: int, fill_percent: float, goal_percent: ?float,
     *     pledges: array<string, array{state: string, amount: ?string, note: string}>
     * }|null null when the thread is not a campaign
     */
    public static function fromPosts(array $posts, string $rootPostId, bool $countUnnamed = false): ?array
    {
        $goal = null;
        foreach ($posts as $post) {
            if ((string) ($post['post_id'] ?? '') === $rootPostId) {
                $goal = ($post['retracted_at'] ?? null) === null ? GoalDirective::extract((string) ($post['body'] ?? '')) : null;
                break;
            }
        }
        if ($goal === null) {
            return null;
        }

        $unit = $goal['unit'];
        $sum = 0;
        $count = 0;
        $pledges = [];
        foreach ($posts as $post) {
            $postId = (string) ($post['post_id'] ?? '');
            if ($postId === $rootPostId || ($post['retracted_at'] ?? null) !== null || !str_contains((string) ($post['body'] ?? ''), ':')) {
                continue;
            }
            $written = PledgeDirective::extract((string) $post['body']);
            if ($written === null) {
                continue;
            }
            $amount = self::amount($written, $unit);
            if ($amount === null) {
                $pledges[$postId] = ['state' => self::UNREADABLE, 'amount' => null, 'note' => self::unreadableNote($written, $unit)];
                continue;
            }
            $state = match (true) {
                !$countUnnamed && (string) ($post['author_identity_id'] ?? '') === '' => self::UNNAMED,
                (int) ($post['attribution_counted'] ?? 1) !== 1 => self::UNVERIFIED,
                default => self::COUNTED,
            };
            $pledges[$postId] = ['state' => $state, 'amount' => PledgeAmount::format($amount), 'note' => match ($state) {
                self::UNNAMED => 'Not counted: no name',
                self::UNVERIFIED => 'Not counted: unverified',
                default => '',
            }];
            if ($state === self::COUNTED) {
                $sum += $amount;
                $count++;
            }
        }

        $scale = max($goal['amount'], $sum);

        return [
            'goal' => PledgeAmount::format($goal['amount']),
            'unit' => $unit,
            'pledged' => PledgeAmount::format($sum),
            'count' => $count,
            'fill_percent' => round($sum / $scale * 100, 1),
            // The goal mark appears once pledges pass the goal; before that the bar's end is the goal.
            'goal_percent' => $sum > $goal['amount'] ? round($goal['amount'] / $scale * 100, 1) : null,
            'pledges' => $pledges,
        ];
    }

    /** A number, optionally followed by the campaign's unit; anything else is not counted. */
    private static function amount(string $written, string $unit): ?int
    {
        $parts = preg_split('/\s+/', $written, 2) ?: [];
        $amount = PledgeAmount::parse((string) ($parts[0] ?? ''));
        $trailing = trim((string) ($parts[1] ?? ''));
        if ($amount === null || ($trailing !== '' && self::unitKey($trailing) !== self::unitKey($unit))) {
            return null;
        }

        return $amount;
    }

    /** "Coffee" and "coffees" are the same unit. */
    private static function unitKey(string $unit): string
    {
        return rtrim(strtolower(trim($unit)), 's');
    }

    private static function unreadableNote(string $written, string $unit): string
    {
        $shown = mb_strlen($written) > 40 ? rtrim(mb_substr($written, 0, 39)) . '…' : $written;
        $first = (string) (preg_split('/\s+/', $written, 2)[0] ?? '');

        return PledgeAmount::parse($first) === null
            ? 'Not counted: “' . $shown . '” is not a number'
            : 'Not counted: “' . $shown . '” is not in ' . ($unit !== '' ? $unit : 'this campaign’s unit');
    }
}
