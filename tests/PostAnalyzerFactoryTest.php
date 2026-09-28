<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Analysis\DedalusPostAnalyzer;
use ForumRewrite\Analysis\PostAnalyzerFactory;
use ForumRewrite\Analysis\StubPostAnalyzer;
use ForumRewrite\Llm\AnthropicStructuredChatProvider;
use ForumRewrite\Llm\OpenAiCompatibleStructuredChatProvider;

final class PostAnalyzerFactoryTest
{
    public function testFactoryReturnsStubAnalyzerForStubProvider(): void
    {
        $analyzer = PostAnalyzerFactory::fromPrivateConfig([
            'LLM_PROVIDER' => 'stub',
        ], dirname(__DIR__));

        assertSame(StubPostAnalyzer::class, $analyzer::class);
    }

    public function testFactoryReturnsNullWhenApiKeyIsMissing(): void
    {
        $analyzer = PostAnalyzerFactory::fromPrivateConfig([
            'LLM_PROVIDER' => 'openai',
        ], dirname(__DIR__));

        assertSame(null, $analyzer);
    }

    public function testFactoryThrowsWhenProviderIsNotConfigured(): void
    {
        $threw = false;
        try {
            PostAnalyzerFactory::fromPrivateConfig([
                'DEDALUS_API_KEY' => 'dedalus-key',
                'DEDALUS_MODEL' => 'openai/gpt-5-nano',
            ], dirname(__DIR__));
        } catch (RuntimeException $exception) {
            $threw = true;
            assertSame(
                'LLM_PROVIDER must be set in the private config (e.g. "anthropic", "openai", "openrouter", or a custom OpenAI-compatible gateway name). There is no default provider.',
                $exception->getMessage()
            );
        }

        assertSame(true, $threw);
    }

    public function testFactoryUsesLegacyDedalusKeysForValuesWhenProviderIsSet(): void
    {
        $analyzer = PostAnalyzerFactory::fromPrivateConfig([
            'LLM_PROVIDER' => 'openai',
            'DEDALUS_API_KEY' => 'dedalus-key',
            'DEDALUS_MODEL' => 'gpt-5-nano',
        ], dirname(__DIR__));
        $provider = $this->providerFromAnalyzer($analyzer);

        assertSame(DedalusPostAnalyzer::class, $analyzer::class);
        assertSame(OpenAiCompatibleStructuredChatProvider::class, $provider::class);
        assertSame('openai', $this->privateProperty($provider, 'providerName'));
        assertSame('https://api.openai.com', $this->privateProperty($provider, 'baseUrl'));
        assertSame('gpt-5-nano', $this->privateProperty($provider, 'model'));
    }

    public function testFactoryBuildsOpenRouterCompatibleProviderWithExtraHeaders(): void
    {
        $analyzer = PostAnalyzerFactory::fromPrivateConfig([
            'LLM_PROVIDER' => 'openrouter',
            'LLM_API_KEY' => 'router-key',
            'LLM_MODEL' => 'anthropic/claude-haiku-4.5',
            'LLM_EXTRA_HEADERS' => [
                'HTTP-Referer' => 'https://forum.example',
            ],
        ], dirname(__DIR__));
        $provider = $this->providerFromAnalyzer($analyzer);

        assertSame(OpenAiCompatibleStructuredChatProvider::class, $provider::class);
        assertSame('openrouter', $this->privateProperty($provider, 'providerName'));
        assertSame('https://openrouter.ai/api', $this->privateProperty($provider, 'baseUrl'));
        assertSame('anthropic/claude-haiku-4.5', $this->privateProperty($provider, 'model'));
        assertSame('https://forum.example', $this->privateProperty($provider, 'extraHeaders')['HTTP-Referer']);
    }

    public function testFactoryBuildsAnthropicProvider(): void
    {
        $analyzer = PostAnalyzerFactory::fromPrivateConfig([
            'LLM_PROVIDER' => 'anthropic',
            'LLM_API_KEY' => 'anthropic-key',
        ], dirname(__DIR__));
        $provider = $this->providerFromAnalyzer($analyzer);

        assertSame(AnthropicStructuredChatProvider::class, $provider::class);
        assertSame('https://api.anthropic.com', $this->privateProperty($provider, 'baseUrl'));
        assertSame('claude-haiku-4-5-20251001', $this->privateProperty($provider, 'model'));
    }

    private function providerFromAnalyzer(object $analyzer): object
    {
        $property = new ReflectionProperty(DedalusPostAnalyzer::class, 'provider');
        $property->setAccessible(true);
        $provider = $property->getValue($analyzer);
        if (!is_object($provider)) {
            throw new RuntimeException('Analyzer provider was not an object.');
        }

        return $provider;
    }

    private function privateProperty(object $object, string $name): mixed
    {
        $property = new ReflectionProperty($object::class, $name);
        $property->setAccessible(true);

        return $property->getValue($object);
    }
}
