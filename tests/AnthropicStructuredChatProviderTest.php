<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\AnthropicStructuredChatProvider;

final class AnthropicStructuredChatProviderTest
{
    public function testProviderBuildsAnthropicStructuredOutputPayload(): void
    {
        $provider = new AnthropicStructuredChatProvider('test-key', 'https://api.anthropic.com', 'claude-haiku-4-5-20251001');
        $method = new ReflectionMethod(AnthropicStructuredChatProvider::class, 'payloadFor');
        $method->setAccessible(true);

        $payload = $method->invoke($provider, 'ForumPostAnalysis', [
            ['role' => 'system', 'content' => 'Analyze.'],
            ['role' => 'user', 'content' => '{"post_id":"post-1"}'],
        ], [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'post_summary' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500],
            ],
            'required' => ['post_summary'],
        ], [
            'max_completion_tokens' => 123,
        ]);

        assertSame('claude-haiku-4-5-20251001', $payload['model']);
        assertSame(123, $payload['max_tokens']);
        assertSame('Analyze.', $payload['system']);
        assertSame('user', $payload['messages'][0]['role']);
        assertSame('json_schema', $payload['output_config']['format']['type']);
        assertSame('object', $payload['output_config']['format']['schema']['type']);
        assertSame(false, isset($payload['output_config']['format']['schema']['properties']['post_summary']['minLength']));
        assertSame(false, isset($payload['output_config']['format']['schema']['properties']['post_summary']['maxLength']));
    }

    public function testProviderDecodesTextJsonResponse(): void
    {
        $provider = new AnthropicStructuredChatProvider('test-key', 'https://api.anthropic.com', 'claude-haiku-4-5-20251001');
        $method = new ReflectionMethod(AnthropicStructuredChatProvider::class, 'decodeResponse');
        $method->setAccessible(true);

        $decoded = $method->invoke($provider, [
            'id' => 'msg_123',
            'model' => 'claude-haiku-4-5-20251001',
            'content' => [
                [
                    'type' => 'text',
                    'text' => '{"post_summary":"Summary."}',
                ],
            ],
        ]);

        assertSame('Summary.', $decoded['post_summary']);
    }

    public function testProviderExtractsAnthropicErrorMessageAndRedactsKey(): void
    {
        $provider = new AnthropicStructuredChatProvider('test-key', 'https://api.anthropic.com', 'claude-haiku-4-5-20251001');
        $errorMethod = new ReflectionMethod(AnthropicStructuredChatProvider::class, 'errorMessageFromResponse');
        $errorMethod->setAccessible(true);
        $headersMethod = new ReflectionMethod(AnthropicStructuredChatProvider::class, 'redactedHeaders');
        $headersMethod->setAccessible(true);

        $message = $errorMethod->invoke($provider, [
            'error' => [
                'message' => 'Invalid request.',
            ],
        ]);
        $headers = $headersMethod->invoke($provider, [
            'x-api-key' => 'secret',
            'anthropic-version' => '2023-06-01',
        ]);

        assertSame('Invalid request.', $message);
        assertSame('<redacted>', $headers['x-api-key']);
        assertSame('2023-06-01', $headers['anthropic-version']);
    }
}
