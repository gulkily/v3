<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Scoring\FastScoringConfig;

final class FastScoringConfigTest
{
    public function testDefaultsToDisabledAndUsesGeneralLlmValuesAsFallback(): void
    {
        $config = FastScoringConfig::fromPrivateConfig([
            'LLM_PROVIDER' => 'openrouter',
            'LLM_API_KEY' => 'general-key',
            'LLM_API_BASE_URL' => 'https://openrouter.ai/api',
            'LLM_MODEL' => 'general-model',
            'LLM_TIMEOUT_SECONDS' => 19,
        ]);

        assertSame(false, $config->enabled);
        assertSame('openrouter', $config->provider->provider);
        assertSame('general-key', $config->provider->apiKey);
        assertSame('general-model', $config->provider->model);
        assertSame(19, $config->provider->timeoutSeconds);
        assertSame('prompts/fastmod_introduction.txt', $config->promptPath);
        assertSame(false, $config->automaticEnqueue);
    }

    public function testBlankModelInheritsAndExplicitGatewayModelIsPreserved(): void
    {
        foreach ([null, '', '   ', 'gateway/custom-model'] as $override) {
            $config = FastScoringConfig::fromPrivateConfig([
                'LLM_PROVIDER' => 'openai', 'LLM_MODEL' => 'gpt-5-nano',
                'FAST_SCORING_LLM_MODEL' => $override,
            ]);
            assertSame($override === 'gateway/custom-model' ? $override : 'gpt-5-nano', $config->provider->model);
        }
    }

    public function testFastScoringKeepsTheApplicationProviderAndMayUseALowerCostModel(): void
    {
        $config = FastScoringConfig::fromPrivateConfig([
            'LLM_PROVIDER' => 'openai',
            'LLM_MODEL' => 'full-analysis-model',
            'FAST_SCORING_ENABLED' => 'yes',
            'FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED' => 'true',
            'FAST_SCORING_LLM_PROVIDER' => 'openai',
            'FAST_SCORING_LLM_API_KEY' => 'fast-key',
            'FAST_SCORING_LLM_API_BASE_URL' => 'https://api.openai.com',
            'FAST_SCORING_LLM_MODEL' => 'fast-model',
            'FAST_SCORING_LLM_TIMEOUT_SECONDS' => 7,
            'FAST_SCORING_LLM_EXTRA_HEADERS' => ['X-Title' => 'Fast scoring'],
            'FAST_SCORING_PROMPT_PATH' => 'prompts/custom_fast_score.txt',
        ]);

        assertSame(true, $config->enabled);
        assertSame('openai', $config->provider->provider);
        assertSame('', $config->provider->apiKey);
        assertSame('https://api.openai.com', $config->provider->baseUrl);
        assertSame('fast-model', $config->provider->model);
        assertSame(60, $config->provider->timeoutSeconds);
        assertSame([], $config->provider->extraHeaders);
        assertSame('prompts/custom_fast_score.txt', $config->promptPath);
        assertSame(true, $config->automaticEnqueue);
    }
}
