<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Agent\DedalusAgentReplyGenerator;
use ForumRewrite\Agent\SqliteAgentReplyGenerationStore;
use ForumRewrite\Agent\StubAgentReplyGenerator;

final class AgentReplyGenerationTest
{
    public function testStoreSavesAndHydratesCompletedGenerationRows(): void
    {
        $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite::memory:'));
        $row = $store->saveComplete($this->context(), [
            'provider' => 'stub',
            'provider_model' => 'stub/agent-reply',
            'provider_request_id' => 'req-1',
            'response_text' => 'A concise generated reply.',
            'response_style' => 'curious',
            'response_intent' => 'answer',
            'raw_response' => ['ok' => true],
        ]);

        $hydrated = $store->findByTarget('root-001', 'hash-001');

        assertSame('complete', $row['status']);
        assertSame('root-001', $hydrated['target_post_id']);
        assertSame('hash-001', $hydrated['target_content_hash']);
        assertSame('analysis-hash-001', $hydrated['analysis_hash']);
        assertSame('stub', $hydrated['provider']);
        assertSame('stub/agent-reply', $hydrated['provider_model']);
        assertSame('req-1', $hydrated['provider_request_id']);
        assertSame('A concise generated reply.', $hydrated['response_text']);
        assertSame('curious', $hydrated['response_style']);
        assertSame('answer', $hydrated['response_intent']);
        assertSame(true, $hydrated['raw_response']['ok']);
    }

    public function testStoreSavesRetryableFailures(): void
    {
        $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite::memory:'));
        $row = $store->saveFailed('root-001', 'hash-001', 'analysis-hash-001', 'provider_error', 'Provider unavailable');

        assertSame('failed', $row['status']);
        assertSame('provider_error', $row['failure_code']);
        assertSame('Provider unavailable', $row['failure_message']);
        assertSame(true, is_string($row['retry_after']));
    }

    public function testStoreReusesDuplicateCompletedGenerationForSameTarget(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new SqliteAgentReplyGenerationStore($pdo);
        $first = $store->saveComplete($this->context(), [
            'provider' => 'stub',
            'provider_model' => 'stub/agent-reply',
            'response_text' => 'First reply.',
            'response_style' => 'curious',
            'response_intent' => 'answer',
        ]);
        $second = $store->saveComplete($this->context(), [
            'provider' => 'stub',
            'provider_model' => 'stub/agent-reply',
            'response_text' => 'Second reply.',
            'response_style' => 'supportive',
            'response_intent' => 'clarify',
        ]);

        assertSame($first['id'], $second['id']);
        assertSame('First reply.', $second['response_text']);
        assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM post_generated_responses')->fetchColumn());
    }

    public function testStoreReservesGenerationOncePerTarget(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new SqliteAgentReplyGenerationStore($pdo);

        $first = $store->reserveGeneration($this->context());
        $second = $store->reserveGeneration($this->context());

        assertSame('pending', $first['status']);
        assertSame(true, $first['reserved']);
        assertSame('pending', $second['status']);
        assertSame(false, $second['reserved']);
        assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM post_generated_responses')->fetchColumn());
    }

    public function testStoreReservesPostingOncePerTarget(): void
    {
        $store = new SqliteAgentReplyGenerationStore(new PDO('sqlite::memory:'));
        $store->saveComplete($this->context(), [
            'provider' => 'stub',
            'provider_model' => 'stub/agent-reply',
            'response_text' => 'First reply.',
            'response_style' => 'curious',
            'response_intent' => 'answer',
        ]);

        $first = $store->reservePosting('root-001', 'hash-001');
        $second = $store->reservePosting('root-001', 'hash-001');

        assertSame('posting', $first['status']);
        assertSame(true, $first['reserved']);
        assertSame('posting', $second['status']);
        assertSame(false, $second['reserved']);
    }

    public function testStubGeneratorReturnsDeterministicStructuredOutput(): void
    {
        $generator = new StubAgentReplyGenerator();
        $first = $generator->generate($this->context());
        $second = $generator->generate($this->context());

        assertSame($first, $second);
        assertSame('stub', $first['provider']);
        assertSame('stub/agent-reply', $first['provider_model']);
        assertSame('curious', $first['response_style']);
        assertStringContains('tradeoffs', $first['response_text']);
    }

    public function testDedalusDecoderAcceptsExpectedResponseShape(): void
    {
        $decoded = DedalusAgentReplyGenerator::decodeCompletionPayload([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'response_text' => 'Here is a concise response.',
                            'response_style' => 'clarifying',
                            'response_intent' => 'ask_followup',
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        ]);

        assertSame('Here is a concise response.', $decoded['response_text']);
        assertSame('clarifying', $decoded['response_style']);
        assertSame('ask_followup', $decoded['response_intent']);
    }

    public function testPromptTemplateLoadsFromFile(): void
    {
        $generator = new DedalusAgentReplyGenerator(
            'test-key',
            'https://dedalus.invalid',
            'test-model',
            1,
            dirname(__DIR__) . '/prompts/dedalus_agent_reply_system.txt'
        );
        $prompt = $generator->systemPrompt();

        assertStringContains('reply-agent', $prompt);
        assertStringContains('curious', $prompt);
        assertStringContains('ask_followup', $prompt);
        assertStringNotContains('{{response_styles}}', $prompt);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [
            'post_id' => 'root-001',
            'content_hash' => 'hash-001',
            'analysis_hash' => 'analysis-hash-001',
            'analysis' => [
                'respondability' => [
                    'best_response_mode' => 'answer',
                ],
            ],
        ];
    }
}
