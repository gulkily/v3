<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Inbox\InboxFeed;
use ForumRewrite\Inbox\InboxMarkStore;
use ForumRewrite\ReadModel\ReadModelSchema;

final class InboxFeedTest
{
    private string $now = '2026-11-07T12:00:00Z';

    public function testRepliesAreRepliesToAnyKeyOfMyUsernameAndNeverMyOwn(): void
    {
        [$read, $feed] = $this->setUp();
        $this->post($read, 'mine-1', '2026-11-07T10:00:00Z', 'k1');
        $this->post($read, 'mine-2', '2026-11-07T10:01:00Z', 'k2');
        $this->now = '2026-11-07T11:00:00Z';
        $feed->items('replies');
        $feed->items('new');
        $this->post($read, 'reply-to-k1', '2026-11-07T11:10:00Z', 'other', 'mine-1');
        $this->post($read, 'reply-to-k2', '2026-11-07T11:11:00Z', 'other', 'mine-2');
        $this->post($read, 'my-own-reply', '2026-11-07T11:12:00Z', 'k2', 'mine-1');
        $this->post($read, 'other-thread', '2026-11-07T11:13:00Z', 'other');
        $this->post($read, 'reply-to-other', '2026-11-07T11:14:00Z', 'guest', 'other-thread');
        $this->post($read, 'anonymous-reply', '2026-11-07T11:15:00Z', null, 'mine-2');

        assertSame(['reply-to-k1', 'reply-to-k2', 'anonymous-reply'], array_column($feed->items('replies'), 'post_id'));
        assertSame(['other-thread', 'reply-to-other'], array_column($feed->items('new'), 'post_id'));
        assertSame(3, $feed->unreadCount('replies'));
    }

    public function testFirstUseMakesEarlierPostsHistory(): void
    {
        [$read, $feed] = $this->setUp();
        $this->post($read, 'mine', '2026-11-01T00:00:00Z', 'k1');
        $this->post($read, 'old-reply', '2026-11-02T00:00:00Z', 'other', 'mine');
        assertSame([], $feed->items('replies'));
        assertSame(0, $feed->unreadCount('replies'));
        $this->post($read, 'new-reply', '2026-11-07T12:00:01Z', 'other', 'mine');
        assertSame(['new-reply'], array_column($feed->items('replies'), 'post_id'));
    }

    public function testCaughtUpHidesExactlyWhatWasShownAndKeepsLateArrivals(): void
    {
        [$read, $feed] = $this->setUp();
        $this->post($read, 'mine', '2026-11-07T11:00:00Z', 'k1');
        $feed->items('replies');
        $this->post($read, 'r1', '2026-11-07T12:01:00Z', 'other', 'mine');
        $this->post($read, 'r2', '2026-11-07T12:05:00Z', 'other', 'mine');
        $shown = array_column($feed->items('replies'), 'post_id');

        // Signed while the page was open, it arrives after the member saw r1 and r2.
        $this->post($read, 'late', '2026-11-07T12:04:00Z', 'other', 'mine');
        $feed->catchUp('replies', [...$shown, 'not-a-reply', 'mine']);

        assertSame(['late'], array_column($feed->items('replies'), 'post_id'));
        assertSame(1, $feed->unreadCount('replies'));
        $feed->catchUp('replies', ['late']);
        assertSame([], $feed->items('replies'));
        assertSame(0, $feed->unreadCount('replies'));

        $this->post($read, 'after', '2026-11-07T12:06:00Z', 'other', 'mine');
        assertSame(['after'], array_column($feed->items('replies'), 'post_id'));
    }

    public function testMarksOnlyMoveForwardAndSectionsAreIndependent(): void
    {
        $this->now = '2026-11-07T12:00:00Z';
        $marks = new InboxMarkStore(new PDO('sqlite::memory:'), fn (): string => $this->now);
        $first = $marks->mark('Alice', 'replies');
        assertSame('2026-11-07T12:00:00Z', $first['mark_at']);
        assertSame('2026-11-07T11:50:00Z', $first['window_start']);
        $marks->catchUp('alice', 'replies', ['p2' => '2026-11-07T13:00:00Z']);
        $marks->catchUp('alice', 'replies', ['p1' => '2026-11-07T12:30:00Z']);
        assertSame('2026-11-07T13:00:00Z', $marks->mark('alice', 'replies')['mark_at']);
        assertSame('2026-11-07T12:00:00Z', $marks->mark('alice', 'new')['mark_at']);
        assertSame(['p2' => true], $marks->mark('alice', 'replies')['acknowledged'], 'Acknowledgments older than the window are dropped.');

        foreach ([['', 'replies'], ['alice', 'messages']] as [$viewer, $section]) {
            try {
                $marks->mark($viewer, $section);
                throw new RuntimeException('Expected rejection.');
            } catch (InvalidArgumentException) {
            }
        }
    }

    /** @return array{0:PDO,1:InboxFeed} */
    private function setUp(): array
    {
        $this->now = '2026-11-07T12:00:00Z';
        $read = new PDO('sqlite::memory:');
        $read->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach (ReadModelSchema::statements() as $statement) $read->exec($statement);
        $marks = new InboxMarkStore(new PDO('sqlite::memory:'), fn (): string => $this->now);
        return [$read, new InboxFeed($read, $marks, 'alice', ['k1', 'k2'])];
    }

    private function post(PDO $read, string $postId, string $createdAt, ?string $author, ?string $parentId = null): void
    {
        $threadId = $parentId === null ? $postId : (string) $read->query('SELECT thread_id FROM posts WHERE post_id = ' . $read->quote($parentId))->fetchColumn();
        $read->prepare("INSERT INTO posts (post_id, created_at, thread_id, parent_id, subject, body, board_tags_json, author_identity_id, sequence_number)
            VALUES (?, ?, ?, ?, ?, ?, '[\"general\"]', ?, 0)")
            ->execute([$postId, $createdAt, $threadId, $parentId, $parentId === null ? 'Subject ' . $postId : null, 'Body ' . $postId, $author]);
    }
}
