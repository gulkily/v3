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

    public function testBothProvidersRetainDependencyContextOnTransportFailures(): void
    {
        foreach (['openai', 'anthropic'] as $name) {
            $pdo = new PDO('sqlite::memory:');
            $recorder = new LlmExchangeRecorder($pdo);
            $provider = $name === 'openai'
                ? new \ForumRewrite\Llm\OpenAiCompatibleStructuredChatProvider('openai', 'test-key', 'http://127.0.0.1:1', 'test', 1, [], $recorder)
                : new \ForumRewrite\Llm\AnthropicStructuredChatProvider('test-key', 'http://127.0.0.1:1', 'test', 1, [], $recorder);
            $manifest = \ForumRewrite\Llm\ExchangeDependencies::manifest(['target', 'parent']);
            try {
                $provider->completeTextChat([['role' => 'user', 'content' => 'Sensitive context']], [
                    'exchange_context' => ['call_type' => 'agent_response_task', 'post_id' => 'target', 'exchange_dependencies' => $manifest],
                ]);
                throw new RuntimeException('Expected failed transport');
            } catch (\ForumRewrite\Analysis\ProviderRequestException) {}
            $row = $pdo->query('SELECT * FROM llm_exchanges')->fetch(PDO::FETCH_ASSOC);
            assertSame('transport_error', $row['status']);
            assertSame($manifest, json_decode($row['context_json'], true)['exchange_dependencies']);
            assertSame(true, str_contains($row['request_json'], 'Sensitive context'));
            assertSame(false, str_contains($row['request_json'], 'test-key'));
        }
    }

    public function testDisabledRecorderDoesNotCreateExchangeRows(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $recorder = new LlmExchangeRecorder($pdo, false);

        assertSame(null, $recorder->record([], [], [], 'completed', 1.0));
        assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM llm_exchanges')->fetchColumn());
    }
}
