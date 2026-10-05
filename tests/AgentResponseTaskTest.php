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

    public function testSelectableModesHaveStableLabelsAndTaskTypes(): void
    {
        $modes = AgentResponseTask::selectableModes();

        assertSame([
            AgentResponseTask::FACTS_ANALYSIS_TYPE,
            AgentResponseTask::EXPLAIN_JOKE_OR_REFERENCE_TYPE,
            AgentResponseTask::SUMMARY_AND_KEY_TAKEAWAYS_TYPE,
            AgentResponseTask::EXPLAIN_SIMPLY_TYPE,
            AgentResponseTask::CONSTRUCTIVE_COUNTERPOINT_TYPE,
        ], array_column($modes, 'type'));
        assertSame('Facts analysis', $modes[0]['label']);
        assertSame('Facts analysis', AgentResponseTask::labelForType(AgentResponseTask::FACTS_ANALYSIS_TYPE));
        assertSame(null, AgentResponseTask::labelForType('unknown'));
        assertSame(false, AgentResponseTask::isSelectableType(AgentResponseTask::DEFAULT_TYPE));
    }

    public function testSelectableTaskCanBeStoredAndHasBoundedGenerationInput(): void
    {
        $task = AgentResponseTask::forPost([
            'post_id' => 'post-123',
            'content_hash' => 'hash-123',
        ], AgentResponseTask::FACTS_ANALYSIS_TYPE);
        $input = AgentResponseTask::generationInputForType([
            'post_id' => 'post-123',
            'content_hash' => 'hash-123',
            'body' => str_repeat('a', 6100),
        ], AgentResponseTask::FACTS_ANALYSIS_TYPE);
        $stored = AgentResponseTask::fromStoredRequestContext([
            'agent_reply_request' => ['agent_response_task' => $task],
        ]);

        assertSame(AgentResponseTask::FACTS_ANALYSIS_TYPE, $task['type']);
        assertSame(AgentResponseTask::FACTS_ANALYSIS_TYPE, $stored['type']);
        assertSame(AgentResponseTask::FACTS_ANALYSIS_TYPE, $input['type']);
        assertSame(6000, strlen($input['target_body']));
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
