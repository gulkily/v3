<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\TaskQueue\AgentReplyTask;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;

final class AgentReplyTaskTest
{
    public function testTaskKeyRoundTripsPostAndContentIdentity(): void
    {
        $key = AgentReplyTask::deduplicationKey('post/with @ punctuation', 'content hash/1');

        assertSame(
            ['post_id' => 'post/with @ punctuation', 'content_hash' => 'content hash/1'],
            AgentReplyTask::targetFromDeduplicationKey($key),
        );
    }

    public function testTaskKeyRejectsMissingOrNonCanonicalIdentity(): void
    {
        $this->assertInvalid(static fn (): string => AgentReplyTask::deduplicationKey('', 'hash'));
        $this->assertInvalid(static fn (): array => AgentReplyTask::targetFromDeduplicationKey('post@hash@extra'));
        $this->assertInvalid(static fn (): array => AgentReplyTask::targetFromDeduplicationKey('post@hash%2fvalue'));
    }

    public function testQueueCoalescesOneOutstandingTaskPerReplyIdentity(): void
    {
        $store = new SqliteTaskQueueStore(new PDO('sqlite::memory:'));
        $key = AgentReplyTask::deduplicationKey('post-001', 'content-001');

        $first = $store->enqueue(SqliteTaskQueueStore::AGENT_REPLY, $key);
        $second = $store->enqueue(SqliteTaskQueueStore::AGENT_REPLY, $key);

        assertSame(true, $first['enqueued']);
        assertSame(false, $second['enqueued']);
        assertSame($first['id'], $second['id']);
    }

    private function assertInvalid(callable $callback): void
    {
        try {
            $callback();
        } catch (InvalidArgumentException) {
            return;
        }

        throw new RuntimeException('Expected invalid agent-reply task identity.');
    }
}
