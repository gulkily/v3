<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\ReadModel\RetractedLeafFilter;

final class RetractedLeafFilterTest
{
    public function testDropsRetractedDeadEndsAndKeepsStubsThatStillHaveReplies(): void
    {
        $reply = static fn (string $id, string $parent, bool $retracted = false): array =>
            ['post_id' => $id, 'parent_id' => $parent, 'retracted_at' => $retracted ? '2026-11-06T22:40:41Z' : null];
        $replies = [
            $reply('a', 'root'),
            $reply('dead', 'root', true),          // retracted leaf: hidden
            $reply('chain1', 'root', true),        // retracted, only answered by a retracted reply: hidden
            $reply('chain2', 'chain1', true),
            $reply('context', 'root', true),       // retracted but answered by a shown reply: stub stays
            $reply('answer', 'context'),
        ];
        assertSame(['a', 'context', 'answer'], array_column(RetractedLeafFilter::apply($replies), 'post_id'));
        assertSame([], RetractedLeafFilter::apply([]));
    }
}
