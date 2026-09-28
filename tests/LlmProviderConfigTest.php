<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\LlmProviderConfig;

final class LlmProviderConfigTest
{
    public function testNeutralConfigValuesAreResolved(): void
    {
        $config = LlmProviderConfig::fromPrivateConfig([
            'LLM_PROVIDER' => 'openrouter',
            'LLM_API_KEY' => 'router-key',
            'LLM_API_BASE_URL' => 'https://openrouter.ai/api',
            'LLM_MODEL' => 'openai/gpt-5-nano',
            'LLM_TIMEOUT_SECONDS' => 17,
            'LLM_EXTRA_HEADERS' => [
                'HTTP-Referer' => 'https://example.test',
                'X-Title' => 'Forum',
            ],
            'LLM_POST_ANALYSIS_PROMPT_PATH' => 'custom-prompt.txt',
        ]);

        assertSame('openrouter', $config->provider);
        assertSame('router-key', $config->apiKey);
        assertSame('https://openrouter.ai/api', $config->baseUrl);
        assertSame('openai/gpt-5-nano', $config->model);
        assertSame(17, $config->timeoutSeconds);
        assertSame('custom-prompt.txt', $config->postAnalysisPromptPath);
        assertSame('Forum', $config->extraHeaders['X-Title']);
    }

    public function testMissingProviderThrowsRequiringExplicitConfiguration(): void
    {
        $threw = false;
        try {
            LlmProviderConfig::fromPrivateConfig([
                'DEDALUS_API_KEY' => 'dedalus-key',
                'DEDALUS_API_BASE_URL' => 'https://api.dedaluslabs.ai',
                'DEDALUS_MODEL' => 'openai/gpt-5-nano',
            ]);
        } catch (RuntimeException $exception) {
            $threw = true;
            assertSame(
                'LLM_PROVIDER must be set in the private config (e.g. "anthropic", "openai", "openrouter", or a custom OpenAI-compatible gateway name). There is no default provider.',
                $exception->getMessage()
            );
        }

        assertSame(true, $threw);
    }

    public function testPlaceholderApiKeyWithoutProviderDoesNotThrow(): void
    {
        $config = LlmProviderConfig::fromPrivateConfig([
            'LLM_PROVIDER' => '',
            'LLM_API_KEY' => 'replace-with-real-key',
        ]);

        assertSame('', $config->provider);
        assertSame('replace-with-real-key', $config->apiKey);
        assertSame('', $config->baseUrl);
        assertSame('', $config->model);
    }

    public function testLegacyDedalusKeysStillPopulateValuesWhenProviderIsSet(): void
    {
        $config = LlmProviderConfig::fromPrivateConfig([
            'LLM_PROVIDER' => 'openai',
            'DEDALUS_API_KEY' => 'dedalus-key',
            'DEDALUS_API_BASE_URL' => 'https://legacy-gateway.example',
            'DEDALUS_MODEL' => 'gpt-5-nano',
            'DEDALUS_TIMEOUT_SECONDS' => 45,
            'DEDALUS_POST_ANALYSIS_PROMPT_PATH' => 'legacy-prompt.txt',
        ]);

        assertSame('openai', $config->provider);
        assertSame('dedalus-key', $config->apiKey);
        assertSame('https://legacy-gateway.example', $config->baseUrl);
        assertSame('gpt-5-nano', $config->model);
        assertSame(45, $config->timeoutSeconds);
        assertSame('legacy-prompt.txt', $config->postAnalysisPromptPath);
    }

    public function testCustomProviderRequiresExplicitBaseUrlAndModel(): void
    {
        $threw = false;
        try {
            LlmProviderConfig::fromPrivateConfig([
                'LLM_PROVIDER' => 'litellm',
                'LLM_API_KEY' => 'gateway-key',
            ]);
        } catch (RuntimeException $exception) {
            $threw = true;
            assertSame('LLM_API_BASE_URL must be set in the private config for provider "litellm".', $exception->getMessage());
        }

        assertSame(true, $threw);
    }

    public function testLegacyStubModeMapsToStubProvider(): void
    {
        $config = LlmProviderConfig::fromPrivateConfig([
            'DEDALUS_ANALYSIS_MODE' => 'stub',
        ]);

        assertSame('stub', $config->provider);
    }

    public function testAnthropicDefaultsUseCurrentHaikuModel(): void
    {
        $config = LlmProviderConfig::fromPrivateConfig([
            'LLM_PROVIDER' => 'anthropic',
            'LLM_API_KEY' => 'anthropic-key',
        ]);

        assertSame('anthropic', $config->provider);
        assertSame('https://api.anthropic.com', $config->baseUrl);
        assertSame('claude-haiku-4-5-20251001', $config->model);
    }
}
