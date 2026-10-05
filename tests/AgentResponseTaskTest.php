<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Agent\AgentResponseTask;

final class AgentResponseTaskTest
{
    public function testDefaultTaskHasStableTypeAndPublicationSlot(): void
    {
        $task = AgentResponseTask::defaultForPost([
            'post_id' => 'post-123',
            'content_hash' => 'hash-123',
        ]);

        assertSame(1, $task['version']);
        assertSame(AgentResponseTask::DEFAULT_TYPE, $task['type']);
        assertSame(AgentResponseTask::PUBLICATION_SLOT, $task['publication_slot']);
        assertSame('post-123', $task['target_post_id']);
        assertSame('hash-123', $task['target_content_hash']);
    }

    public function testStoredDefaultTaskIsRecognized(): void
    {
        $task = AgentResponseTask::fromStoredRequestContext([
            'agent_reply_request' => [
                'agent_response_task' => [
                    'version' => 1,
                    'type' => AgentResponseTask::DEFAULT_TYPE,
                    'target_post_id' => 'post-123',
                    'target_content_hash' => 'hash-123',
                ],
            ],
        ]);

        assertSame(AgentResponseTask::DEFAULT_TYPE, $task['type']);
        assertSame(AgentResponseTask::PUBLICATION_SLOT, $task['publication_slot']);
        assertSame('post-123', $task['target_post_id']);
        assertSame('hash-123', $task['target_content_hash']);
    }

    public function testUnmarkedOrInvalidStoredTaskRemainsLegacy(): void
    {
        $legacy = AgentResponseTask::fromStoredRequestContext([]);
        $invalid = AgentResponseTask::fromStoredRequestContext([
            'agent_reply_request' => [
                'agent_response_task' => ['type' => 'unknown'],
            ],
        ]);

        assertSame(AgentResponseTask::LEGACY_TYPE, $legacy['type']);
        assertSame(AgentResponseTask::LEGACY_TYPE, $invalid['type']);
        assertSame(AgentResponseTask::PUBLICATION_SLOT, $legacy['publication_slot']);
        assertSame(AgentResponseTask::PUBLICATION_SLOT, $invalid['publication_slot']);
    }
}
