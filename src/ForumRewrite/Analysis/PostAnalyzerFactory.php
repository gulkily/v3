<?php

declare(strict_types=1);

namespace ForumRewrite\Analysis;

use ForumRewrite\Llm\AnthropicStructuredChatProvider;
use ForumRewrite\Llm\LlmExchangeRecorder;
use ForumRewrite\Llm\LlmProviderConfig;
use ForumRewrite\Llm\OpenAiCompatibleStructuredChatProvider;
use ForumRewrite\Llm\StructuredChatProvider;

final class PostAnalyzerFactory
{
    /**
     * @param array<string, mixed> $privateConfig
     */
    public static function fromPrivateConfig(array $privateConfig, string $projectRoot, ?LlmExchangeRecorder $exchangeRecorder = null): ?PostAnalyzer
    {
        $config = LlmProviderConfig::fromPrivateConfig($privateConfig);
        if ($config->provider === 'stub') {
            return new StubPostAnalyzer();
        }

        if ($config->apiKey === '') {
            return null;
        }

        return new DedalusPostAnalyzer(
            $config->apiKey,
            systemPromptTemplatePath: self::promptTemplatePath($projectRoot, $config->postAnalysisPromptPath),
            provider: self::structuredChatProvider($config, $exchangeRecorder),
        );
    }

    private static function structuredChatProvider(LlmProviderConfig $config, ?LlmExchangeRecorder $exchangeRecorder = null): StructuredChatProvider
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

    private static function promptTemplatePath(string $projectRoot, string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim($projectRoot, '/') . '/' . $path;
    }
}
