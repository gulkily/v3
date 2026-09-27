<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

use ForumRewrite\Llm\AnthropicStructuredChatProvider;
use ForumRewrite\Llm\LlmExchangeRecorder;
use ForumRewrite\Llm\LlmProviderConfig;
use ForumRewrite\Llm\OpenAiCompatibleStructuredChatProvider;
use ForumRewrite\Llm\StructuredChatProvider;

final class FastScoreWorkflowFactory
{
    /**
     * @param array<string, mixed> $privateConfig
     * @param \Closure(string): (array<string, mixed>|null) $fetchPost
     */
    public static function fromPrivateConfig(
        array $privateConfig,
        string $projectRoot,
        \Closure $fetchPost,
        ?LlmExchangeRecorder $exchangeRecorder = null,
    ): FastScoreWorkflowService {
        $config = FastScoringConfig::fromPrivateConfig($privateConfig);

        return new FastScoreWorkflowService(
            $config,
            new FastScoreContextFactory($fetchPost),
            new DeterministicFastScoreEvaluator(),
            self::scorer($config, $projectRoot, $exchangeRecorder),
        );
    }

    private static function scorer(FastScoringConfig $config, string $projectRoot, ?LlmExchangeRecorder $exchangeRecorder): ?FastScoreProvider
    {
        if (!$config->enabled || $config->provider->provider === 'stub' || $config->provider->apiKey === '') {
            return null;
        }

        $promptPath = self::promptPath($projectRoot, $config->promptPath);
        $prompt = @file_get_contents($promptPath);
        if ($prompt === false || trim($prompt) === '') {
            return null;
        }

        return new FastPostScorer(self::provider($config->provider, $exchangeRecorder), trim($prompt));
    }

    private static function provider(LlmProviderConfig $config, ?LlmExchangeRecorder $exchangeRecorder): StructuredChatProvider
    {
        if ($config->provider === 'anthropic') {
            return new AnthropicStructuredChatProvider(
                $config->apiKey,
                $config->baseUrl,
                $config->model,
                $config->timeoutSeconds,
                $config->extraHeaders,
                $exchangeRecorder,
            );
        }

        return new OpenAiCompatibleStructuredChatProvider(
            $config->provider,
            $config->apiKey,
            $config->baseUrl,
            $config->model,
            $config->timeoutSeconds,
            $config->extraHeaders,
            $exchangeRecorder,
        );
    }

    private static function promptPath(string $projectRoot, string $path): string
    {
        return str_starts_with($path, '/') ? $path : rtrim($projectRoot, '/') . '/' . $path;
    }
}
