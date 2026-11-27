<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';
require_once __DIR__ . '/SupportPageTest.php';

use ForumRewrite\View\PledgeCampaign;
use ForumRewrite\View\SupportWall;

final class SupportWallTest
{
    public function testEachPledgeIsOneBlockOfItsWholeSquaresInThreadOrder(): void
    {
        $wall = $this->wall([
            ['root', null, 'ilya', "Keep the work going.\ngoal: 50 squares"],
            ['mara', 'root', 'mara', 'pledge: 3'],
            ['mara-filled', 'mara', 'mara', 'filled'],
            ['rin', 'root', 'rin', 'pledge: 1'],
            ['sam', 'root', 'sam', 'pledge: 2.5'],
            ['newcomer', 'root', 'newcomer', 'pledge: 1', ['author_is_approved' => 0]],
            ['ann', 'root', 'ann', 'pledge: 2'],
            ['ann-filled', 'ann', 'ann', 'filled: 1'],
            ['ann-withdraw', 'ann', 'ann', 'withdraw'],
            ['lee', 'root', 'lee', 'pledge: 0.5'],
            ['nobody', 'root', null, 'pledge: 2'],
            ['words', 'root', 'kit', 'pledge: a few'],
        ]);

        // Counted blocks first, in thread order; the pending pledge comes after them.
        supportAssertSame(['mara', 'rin', 'sam', 'ann', 'newcomer'], array_column($wall['blocks'], 'post_id'));
        supportAssertSame([3, 1, 2, 1, 1], array_column($wall['blocks'], 'squares'));
        // Delivered squares: all of mara's; ann withdrew, keeping the one square delivered.
        supportAssertSame([3, 0, 0, 1, 0], array_column($wall['blocks'], 'delivered'));
        supportAssertSame([false, false, false, false, true], array_column($wall['blocks'], 'pending'));
        // What is left of a square is named under the wall, not drawn.
        supportAssertSame(['sam', 'lee'], $wall['also']);
        supportAssertSame(8, $wall['drawn']);
        supportAssertSame(50, $wall['size']);
        supportAssertSame(50, $wall['goal']);
    }

    public function testTheWallGrowsARowAtATimePastTheGoal(): void
    {
        $wall = $this->wall([
            ['root', null, 'ilya', 'goal: 5 squares'],
            ['big', 'root', 'mara', 'pledge: 11'],
        ]);
        supportAssertSame(11, $wall['drawn']);
        supportAssertSame(20, $wall['size']);
        supportAssertSame(5, $wall['goal']);
    }

    public function testThePageDrawsBlocksAndMarksTheGoal(): void
    {
        $posts = $this->posts([
            ['root', null, 'ilya', 'goal: 10 squares'],
            ['mara', 'root', 'mara', 'pledge: 3'],
            ['mara-filled', 'mara', 'mara', 'filled: 2'],
            ['newcomer', 'root', 'newcomer', 'pledge: 1', ['author_is_approved' => 0]],
        ]);
        $wall = SupportWall::build((array) PledgeCampaign::fromPosts($posts, 'root'), $posts);
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        ob_start();
        $supportWall = $wall;
        $supportThreadId = 'root';
        include __DIR__ . '/../templates/partials/support_wall.php';
        $html = (string) ob_get_clean();

        supportAssertSame(3, substr_count($html, 'data-block="mara"'));
        supportAssertSame(2, substr_count($html, 'support-wall__square--delivered'));
        supportAssertSame(1, substr_count($html, 'support-wall__square--open'));
        // The name is drawn once per block, on its first square; every square links to the pledge.
        supportAssertSame(1, substr_count($html, '<span class="support-wall__name">mara</span>'));
        supportAssertSame(3, substr_count($html, 'href="/threads/root#post-mara"'));
        supportAssertSame(4, substr_count($html, 'support-wall__badge'));
        supportAssertContains('<span class="support-wall__name">newcomer</span> <span class="chip chip--pending">Pending</span>', $html);
        supportAssertSame(10, substr_count($html, '<li class="support-wall__square'));
        supportAssertSame(1, substr_count($html, 'support-wall__square--goal'));
        supportAssertContains('aria-label="4 of 10 squares covered"', $html);
        supportAssertNotContains('Also:', $html);
    }

    /** @param list<array{0: string, 1: ?string, 2: ?string, 3: string, 4?: array<string, mixed>}> $rows */
    private function wall(array $rows): array
    {
        $posts = $this->posts($rows);

        return SupportWall::build((array) PledgeCampaign::fromPosts($posts, 'root'), $posts);
    }

    /** @param list<array{0: string, 1: ?string, 2: ?string, 3: string, 4?: array<string, mixed>}> $rows */
    private function posts(array $rows): array
    {
        $posts = [];
        foreach ($rows as $row) {
            [$id, $parent, $person, $body] = $row;
            $posts[] = ($row[4] ?? []) + [
                'post_id' => $id, 'thread_id' => 'root', 'parent_id' => $parent ?? 'root', 'body' => $body, 'retracted_at' => null,
                'author_identity_id' => $person === null ? null : 'openpgp:' . $person, 'author_label' => $person ?? 'guest',
                'attribution_counted' => 1, 'author_is_approved' => 1,
            ];
        }

        return $posts;
    }
}
