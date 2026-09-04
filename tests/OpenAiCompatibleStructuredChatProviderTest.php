<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\OpenAiCompatibleStructuredChatProvider;

final class OpenAiCompatibleStructuredChatProviderTest
{
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
