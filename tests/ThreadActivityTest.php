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

        // Record IDs attach to the matching actor only; names without one keep their profile link.
        $recordIds = [\ForumRewrite\Canonical\PostRecordList::reactionKey('root', 'like', 'openpgp:b') => 'thread-label-b',
            \ForumRewrite\Canonical\PostRecordList::reactionKey('r2', 'flag', 'openpgp:h') => 'post-reaction-h'];
        $linked = ThreadActivity::partition($root, $replies, $actors, true, $recordIds)['entries'];
        assertSame([null, 'thread-label-b'], array_map(static fn (array $a): ?string => $a['record_id'] ?? null, $linked[1]['like']));
        assertSame(false, isset($linked[1]['flag'][0]['record_id']));
        assertSame('post-reaction-h', $linked[3]['flag'][0]['record_id']);
        assertSame(false, isset($entries[1]['like'][1]['record_id']));

        assertSame([], ThreadActivity::partition($root, [$post('r1', 'Hi', 1)], ['thread_likes' => [], 'posts' => []], false)['entries']);
    }

    public function testFailureKeepsEveryReplyAndNoEntries(): void
    {
        $replies = [['post_id' => 'tag', 'parent_id' => 'root', 'body' => '#bug', 'retracted_at' => null]];
        $parts = ThreadActivity::partition(['post_id' => 'root'], $replies, ['thread_likes' => 'broken'], false);
        assertSame(['shown' => $replies, 'moved' => [], 'entries' => []], $parts);
    }

    public function testTitleOnlyReplyBecomesTaggedEntryWithTitle(): void
    {
        $post = static fn (string $id, string $body, int $seq): array =>
            ['post_id' => $id, 'parent_id' => 'root', 'body' => $body, 'sequence_number' => $seq, 'author_label' => 'e', 'author_profile_slug' => 'e', 'created_at' => '2026-11-18T10:00:00Z', 'retracted_at' => null];
        $replies = [$post('r1', 'Hi', 1), $post('t', "title: Better name\n#meta", 2)];
        $actors = ['thread_likes' => [], 'posts' => []];

        assertSame(['r1', 't'], array_column(ThreadActivity::partition(['post_id' => 'root'], $replies, $actors, false)['shown'], 'post_id'));
        $parts = ThreadActivity::partition(['post_id' => 'root'], $replies, $actors, false, [], true);
        assertSame(['r1'], array_column($parts['shown'], 'post_id'));
        assertSame([['tagged', 'Better name', ['meta']]], array_map(static fn (array $e): array => [$e['kind'], $e['title'], $e['labels']], $parts['entries']));
    }
}
