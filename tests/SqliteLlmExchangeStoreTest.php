<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\LlmExchangeRecorder;
use ForumRewrite\Llm\SqliteLlmExchangeStore;

final class SqliteLlmExchangeStoreTest
{
    public function testStoreListsFindsAndHydratesExchanges(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $recorder = new LlmExchangeRecorder($pdo);
        $firstId = $recorder->record(
            ['call_type' => 'post_analysis', 'post_id' => 'post-1'],
            ['messages' => [['role' => 'user', 'content' => 'Prompt 1']]],
            ['body' => 'Response 1'],
            'completed',
            1.5
        );
        $secondId = $recorder->record(
            ['call_type' => 'agent_reply_generation', 'post_id' => 'post-2'],
            ['messages' => [['role' => 'user', 'content' => 'Prompt 2']]],
            ['body' => 'Response 2'],
            'provider_error',
            2.5
        );

        $store = new SqliteLlmExchangeStore($pdo);
        $recent = $store->recent();
        $first = $store->find((int) $firstId);
        $postRows = $store->forPost('post-1');

        assertSame(2, count($recent));
        assertSame((int) $secondId, $recent[0]['id']);
        assertSame('Prompt 1', $first['request']['messages'][0]['content']);
        assertSame('Response 1', $first['response']['body']);
        assertSame('post-1', $postRows[0]['related_post_id']);
    }
}
