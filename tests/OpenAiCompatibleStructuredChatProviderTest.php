<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\OpenAiCompatibleStructuredChatProvider;

final class OpenAiCompatibleStructuredChatProviderTest
{
    public function testExplicitReasoningAppliesOnlyToSupportedTextRequests(): void
    {
        $provider = new OpenAiCompatibleStructuredChatProvider('openai', 'test-key', 'https://api.openai.com', 'gpt-5-nano');
        $method = new ReflectionMethod($provider, 'textPayloadFor');
        $payload = $method->invoke($provider, [], ['reasoning_effort' => 'low', 'max_completion_tokens' => 8192]);
        assertSame('low', $payload['reasoning_effort']);
        assertSame(8192, $payload['max_completion_tokens']);
        assertSame(false, isset($method->invoke($provider, [], [])['reasoning_effort']));
        $structured = (new ReflectionMethod($provider, 'payloadFor'))->invoke($provider, 'Score', [], [], ['reasoning_effort' => 'low']);
        assertSame(false, isset($structured['reasoning_effort']));
        try {
            $method->invoke($provider, [], ['reasoning_effort' => 'none']);
            throw new RuntimeException('Unsupported effort accepted');
        } catch (InvalidArgumentException $expected) {
            assertStringContains('Unsupported explicit reasoning', $expected->getMessage());
        }
    }

    public function testScriptureWriterOverrideLeavesSharedAndModerationModelsIntact(): void
    {
        $provider = new OpenAiCompatibleStructuredChatProvider('openai', 'test-key', 'https://api.openai.com', 'gpt-5-mini');
        $method = new ReflectionMethod($provider, 'textPayloadFor');
        $options = ['scripture_writer_model' => 'gpt-5.6-sol', 'reasoning_effort' => 'low', 'max_completion_tokens' => 8192];
        $payload = $method->invoke($provider, [], $options);
        assertSame('gpt-5.6-sol', $payload['model']);
        assertSame('low', $payload['reasoning_effort']);
        assertSame(false, isset($payload['scripture_writer_model']));
        assertSame('gpt-5-mini', $method->invoke($provider, [], [])['model']);
        assertSame('gpt-5-mini', (new ReflectionMethod($provider, 'payloadFor'))->invoke($provider, 'Score', [], [], $options)['model']);
        foreach (['minimal', 'high'] as $effort) {
            try {
                $method->invoke($provider, [], array_replace($options, ['reasoning_effort' => $effort]));
                throw new RuntimeException('Untested Sol effort accepted');
            } catch (InvalidArgumentException) {}
        }
        try {
            $method->invoke($provider, [], ['scripture_writer_model' => 'gpt-unapproved']);
            throw new RuntimeException('Unknown writer accepted');
        } catch (InvalidArgumentException) {}
    }

    public function testProviderBuildsJsonSchemaChatCompletionPayload(): void
    {
        $provider = new OpenAiCompatibleStructuredChatProvider('openrouter', 'test-key', 'https://openrouter.ai/api', 'openai/gpt-5-nano');
        $method = new ReflectionMethod(OpenAiCompatibleStructuredChatProvider::class, 'payloadFor');
        $method->setAccessible(true);

        $payload = $method->invoke($provider, 'ForumPostAnalysis', [
            ['role' => 'system', 'content' => 'Analyze.'],
            ['role' => 'user', 'content' => '{"post_id":"post-1"}'],
        ], [
            'type' => 'object',
            'properties' => [
                'post_summary' => ['type' => 'string'],
            ],
        ], [
            'max_completion_tokens' => 123,
        ]);

        assertSame('openai/gpt-5-nano', $payload['model']);
        assertSame('Analyze.', $payload['messages'][0]['content']);
        assertSame('json_schema', $payload['response_format']['type']);
        assertSame('ForumPostAnalysis', $payload['response_format']['json_schema']['name']);
        assertSame('object', $payload['response_format']['json_schema']['schema']['type']);
        assertSame(123, $payload['max_completion_tokens']);
    }

    public function testProviderBuildsPlainTextChatCompletionPayloadAndDecodesText(): void
    {
        $provider = new OpenAiCompatibleStructuredChatProvider('openrouter', 'test-key', 'https://openrouter.ai/api', 'openai/gpt-5-nano');
        $method = new ReflectionMethod(OpenAiCompatibleStructuredChatProvider::class, 'textPayloadFor');
        $method->setAccessible(true);

        $payload = $method->invoke($provider, [['role' => 'user', 'content' => 'Reply.']], ['max_completion_tokens' => 123]);
        $text = \ForumRewrite\Llm\TextChatCompletionDecoder::decodeOpenAiCompatiblePayload([
            'choices' => [['message' => ['content' => 'Plain reply.']]],
        ]);

        assertSame('openai/gpt-5-nano', $payload['model']);
        assertSame('Reply.', $payload['messages'][0]['content']);
        assertSame(false, isset($payload['response_format']));
        assertSame(123, $payload['max_completion_tokens']);
        assertSame('Plain reply.', $text);
    }

    public function testProviderExtractsNestedProviderErrorMessage(): void
    {
        $provider = new OpenAiCompatibleStructuredChatProvider('dedalus', 'test-key', 'https://api.dedaluslabs.ai', 'openai/gpt-5-nano');
        $method = new ReflectionMethod(OpenAiCompatibleStructuredChatProvider::class, 'errorMessageFromResponse');
        $method->setAccessible(true);

        $message = $method->invoke($provider, [
            'detail' => [
                'error' => [
                    'message' => 'Service unavailable.',
                    'request_id' => 'request-001',
                ],
            ],
        ]);

        assertSame('Service unavailable.', $message);
    }

    public function testProviderRedactsSensitiveHeaders(): void
    {
        $provider = new OpenAiCompatibleStructuredChatProvider('openai-compatible', 'test-key', 'https://example.test', 'test-model');
        $method = new ReflectionMethod(OpenAiCompatibleStructuredChatProvider::class, 'redactedHeaders');
        $method->setAccessible(true);

        $headers = $method->invoke($provider, [
            'Authorization' => 'Bearer secret',
            'X-API-Key' => 'secret',
            'HTTP-Referer' => 'https://example.test',
        ]);

        assertSame('<redacted>', $headers['Authorization']);
        assertSame('<redacted>', $headers['X-API-Key']);
        assertSame('https://example.test', $headers['HTTP-Referer']);
    }
}
