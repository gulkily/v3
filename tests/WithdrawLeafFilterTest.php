<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\ReadModel\WithdrawLeafFilter;

final class WithdrawLeafFilterTest
{
    public function testFoldsOnlyBareWithdrawalsThatTookEffectAndThatNobodyAnswered(): void
    {
        $reply = static fn (string $id, string $parent, string $body): array => ['post_id' => $id, 'parent_id' => $parent, 'body' => $body];
        $replies = [
            $reply('pledge', 'root', 'pledge: 300'),
            $reply('bare', 'pledge', 'withdraw'),                         // folds
            $reply('noted', 'pledge', "withdraw\n\nThe order fell through."), // says something: stays
            $reply('answered', 'pledge', 'Withdraw:'),                    // someone answered it: stays
            $reply('answer', 'answered', 'Sorry to hear that.'),
            $reply('refused', 'pledge', 'withdraw'),                      // did not take effect: its reason must show
            $reply('chain', 'pledge', 'withdraw'),                        // answered only by a folded withdrawal: both fold
            $reply('chain2', 'chain', 'withdraw'),
            $reply('talk', 'root', 'withdraw'),                           // not read as a withdrawal at all
        ];
        $took = ['effective' => true, 'note' => ''];
        $withdrawals = [
            'bare' => $took, 'noted' => $took, 'answered' => $took, 'chain' => $took, 'chain2' => $took,
            'refused' => ['effective' => false, 'note' => 'Not counted: only the pledger can withdraw'],
        ];

        assertSame(
            ['pledge', 'noted', 'answered', 'answer', 'refused', 'talk'],
            array_column(WithdrawLeafFilter::apply($replies, $withdrawals), 'post_id')
        );
        assertSame($replies, WithdrawLeafFilter::apply($replies, []));
        assertSame([], WithdrawLeafFilter::apply([], $withdrawals));
    }
}
