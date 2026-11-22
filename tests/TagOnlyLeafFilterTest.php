<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\ReadModel\TagOnlyLeafFilter;

final class TagOnlyLeafFilterTest
{
    public function testMovesTagOnlyLeavesAndKeepsAnsweredOnes(): void
    {
        $reply = static fn (string $id, string $parent, string $body): array =>
            ['post_id' => $id, 'parent_id' => $parent, 'body' => $body, 'retracted_at' => null];
        $replies = [
            $reply('text', 'root', 'Hello'),
            $reply('leaf', 'root', '#bug'),                // tag-only, unanswered: moved
            $reply('answered', 'root', '#ux'),             // tag-only, answered by text: stays
            $reply('answer', 'answered', 'Why ux?'),
            $reply('chain1', 'root', '#a'),                // tag-only, answered only by a tag-only leaf: moved
            $reply('chain2', 'chain1', '#b'),
            $reply('tagged-text', 'root', '#bug see above'),
        ];
        $split = TagOnlyLeafFilter::split($replies);
        assertSame(['text', 'answered', 'answer', 'tagged-text'], array_column($split['kept'], 'post_id'));
        assertSame(['leaf', 'chain1', 'chain2'], array_column($split['moved'], 'post_id'));
        assertSame(['kept' => [], 'moved' => []], TagOnlyLeafFilter::split([]));
    }

    public function testTitleOnlyRepliesMoveOnlyWhenAsked(): void
    {
        $reply = static fn (string $id, string $body): array =>
            ['post_id' => $id, 'parent_id' => 'root', 'body' => $body, 'retracted_at' => null];
        $replies = [
            $reply('title', 'title: New name'),
            $reply('title-tag', "title: New name\n#bug"),
            $reply('title-text', "title: New name\nAnd some words"),
            $reply('two-titles', "title: One\ntitle: Two"),
            $reply('quoted', '> title: New name'),
        ];
        assertSame([], TagOnlyLeafFilter::split($replies)['moved']);
        $split = TagOnlyLeafFilter::split($replies, true);
        assertSame(['title', 'title-tag'], array_column($split['moved'], 'post_id'));
        assertSame(['title-text', 'two-titles', 'quoted'], array_column($split['kept'], 'post_id'));
    }
}
