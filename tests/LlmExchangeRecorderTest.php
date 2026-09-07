<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\LlmExchangeRecorder;

final class LlmExchangeRecorderTest
{
    public function testRecorderStoresExactRequestAndResponseWithoutCredentials(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $recorder = new LlmExchangeRecorder($pdo);

        $id = $recorder->record(
            [
                'call_type' => 'post_analysis',
                'post_id' => 'post-1',
                'content_hash' => 'hash-1',
                'provider' => 'openai',
                'provider_model' => 'test-model',
                'provider_request_id' => 'req-1',
            ],
            [
                'headers' => ['Authorization' => 'Bearer <redacted>'],
                'payload' => ['messages' => [['role' => 'user', 'content' => 'Exact prompt.']]],
            ],
            ['body' => '{"id":"req-1","result":"Exact response."}'],
            'completed',
            12.3
        );

        $row = $pdo->query('SELECT * FROM llm_exchanges WHERE id = ' . (int) $id)->fetch();

        assertSame('post_analysis', $row['call_type']);
        assertSame('post-1', $row['related_post_id']);
        assertSame('Exact prompt.', json_decode($row['request_json'], true)['payload']['messages'][0]['content']);
        assertSame('{"id":"req-1","result":"Exact response."}', json_decode($row['response_json'], true)['body']);
        assertSame(false, str_contains($row['request_json'], 'Bearer test-key'));
    }

    public function testDisabledRecorderDoesNotCreateExchangeRows(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $recorder = new LlmExchangeRecorder($pdo, false);

        assertSame(null, $recorder->record([], [], [], 'completed', 1.0));
        assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM llm_exchanges')->fetchColumn());
    }
}
