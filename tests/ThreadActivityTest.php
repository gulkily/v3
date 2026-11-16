<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\View\ThreadActivity;

final class ThreadActivityTest
{
    public function testBuildsTaggedEntriesThenReactionGroupsInThreadOrder(): void
    {
        $actor = static fn (string $name): array => ['username' => $name, 'profile_slug' => $name, 'identity_id' => 'openpgp:' . $name];
        $post = static fn (string $id, string $body, int $seq, string $author = 'd'): array =>
            ['post_id' => $id, 'parent_id' => 'root', 'body' => $body, 'sequence_number' => $seq, 'author_label' => $author, 'author_profile_slug' => $author, 'created_at' => '2026-11-14T10:00:00Z', 'retracted_at' => null];
        $root = $post('root', 'Hello', 0, 'a');
        $replies = [$post('r1', 'Hi', 1), $post('tag', '#bug #ux', 2, 'e'), $post('r2', 'Bye', 3, 'f')];
        $actors = [
            'thread_likes' => [$actor('a'), $actor('b')],
            'posts' => ['root' => ['like' => [$actor('x')], 'flag' => [$actor('c')]], 'r1' => ['like' => [], 'flag' => []],
                'tag' => ['like' => [$actor('g')], 'flag' => []], 'r2' => ['like' => [], 'flag' => [$actor('h')]]],
            'title_author' => null,
        ];

        $parts = ThreadActivity::partition($root, $replies, $actors, false);
        assertSame(['r1', 'r2'], array_column($parts['shown'], 'post_id'));
        assertSame(['tag'], array_column($parts['moved'], 'post_id'));
        $entries = $parts['entries'];
        assertSame(['tagged', 'reactions', 'reactions', 'reactions'], array_column($entries, 'kind'));
        assertSame(['bug', 'ux'], $entries[0]['labels']);
        assertSame('e', $entries[0]['author']['username']);
        assertSame('/posts/tag', $entries[0]['href']);
        // Root: thread likes (as on the root signal line) and the root post's flags.
        assertSame(['Root post', ['a', 'b'], ['c']], [$entries[1]['label'], array_column($entries[1]['like'], 'username'), array_column($entries[1]['flag'], 'username')]);
        assertSame(['tag', 'Reply by e'], [$entries[2]['post_id'], $entries[2]['label']]);
        assertSame(['r2', 'Reply by f', '/posts/r2'], [$entries[3]['post_id'], $entries[3]['label'], $entries[3]['href']]);
        assertSame('/posts/r2#records', ThreadActivity::partition($root, $replies, $actors, true)['entries'][3]['href']);

        assertSame([], ThreadActivity::partition($root, [$post('r1', 'Hi', 1)], ['thread_likes' => [], 'posts' => []], false)['entries']);
    }

    public function testFailureKeepsEveryReplyAndNoEntries(): void
    {
        $replies = [['post_id' => 'tag', 'parent_id' => 'root', 'body' => '#bug', 'retracted_at' => null]];
        $parts = ThreadActivity::partition(['post_id' => 'root'], $replies, ['thread_likes' => 'broken'], false);
        assertSame(['shown' => $replies, 'moved' => [], 'entries' => []], $parts);
    }
}
