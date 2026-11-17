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

    public function testFoldsBareFillsByTheSameRule(): void
    {
        $reply = static fn (string $id, string $parent, string $body): array => ['post_id' => $id, 'parent_id' => $parent, 'body' => $body];
        $replies = [
            $reply('pledge', 'root', 'pledge: 300'),
            $reply('bare', 'pledge', 'filled'),                     // folds
            $reply('partial', 'pledge', 'filled: 100'),             // carries an amount: stays
            $reply('noted', 'pledge', "filled\n\nArrived Tuesday."), // says something: stays
            $reply('answered', 'pledge', 'Filled:'),                // someone answered it: stays
            $reply('answer', 'answered', 'Thanks!'),
            $reply('refused', 'pledge', 'filled'),                  // did not count: its reason must show
            $reply('withdraw', 'pledge', 'withdraw'),               // answered by a folded fill: both fold
            $reply('fill-on-withdraw', 'withdraw', 'filled'),
        ];
        $took = ['effective' => true, 'note' => ''];
        $fills = [
            'bare' => $took, 'partial' => $took, 'noted' => $took, 'answered' => $took, 'fill-on-withdraw' => $took,
            'refused' => ['effective' => false, 'note' => 'Not counted: already filled'],
        ];

        assertSame(
            ['pledge', 'partial', 'noted', 'answered', 'answer', 'refused'],
            array_column(WithdrawLeafFilter::apply($replies, ['withdraw' => $took], $fills), 'post_id')
        );
        // A withdrawal map alone does not fold fill replies.
        assertSame(
            ['pledge', 'bare', 'partial', 'noted', 'answered', 'answer', 'refused'],
            array_column(WithdrawLeafFilter::apply(array_slice($replies, 0, 7), ['bare' => $took]), 'post_id')
        );
    }
    public function testFoldsBareConfirmsButNeverADispute(): void
    {
        $reply = static fn (string $id, string $parent, string $body): array => ['post_id' => $id, 'parent_id' => $parent, 'body' => $body];
        $replies = [
            $reply('pledge', 'root', 'pledge: 300'),
            $reply('fill', 'pledge', 'filled'),                   // folds once its bare confirm folds
            $reply('confirm', 'fill', 'confirm'),                 // folds
            $reply('fill2', 'pledge', 'filled: 100'),
            $reply('noted', 'fill2', "confirm\n\nThanks, got them."), // says something: stays
            $reply('fill3', 'pledge', 'filled'),                  // answered by a dispute: stays
            $reply('dispute', 'fill3', 'dispute'),                // never folds
            $reply('refused', 'fill3', 'confirm'),                // did not count: its reason must show
        ];
        $took = ['effective' => true, 'note' => ''];
        $fills = ['fill' => $took, 'fill2' => $took, 'fill3' => ['effective' => false, 'note' => 'Not counted: disputed by ilyag']];
        $reviews = ['confirm' => $took, 'noted' => $took, 'dispute' => $took,
            'refused' => ['effective' => false, 'note' => 'Not counted: only the author or an operator can review']];

        assertSame(
            ['pledge', 'fill2', 'noted', 'fill3', 'dispute', 'refused'],
            array_column(WithdrawLeafFilter::apply($replies, [], $fills, $reviews), 'post_id')
        );
    }
}
