<?php

declare(strict_types=1);

namespace ForumRewrite\Llm;

final class LlmProviderConfig
{
    /**
     * @param array<string, string> $extraHeaders
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $apiKey,
        public readonly string $baseUrl,
        public readonly string $model,
        public readonly int $timeoutSeconds,
        public readonly string $postAnalysisPromptPath,
        public readonly array $extraHeaders = [],
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromPrivateConfig(array $config): self
    {
        $legacyMode = strtolower(trim((string) ($config['DEDALUS_ANALYSIS_MODE'] ?? '')));
        $provider = self::stringValue($config['LLM_PROVIDER'] ?? null);
        if ($provider === '') {
            $provider = $legacyMode === 'stub' ? 'stub' : 'dedalus';
        }

        return new self(
            strtolower($provider),
            self::stringValue($config['LLM_API_KEY'] ?? $config['DEDALUS_API_KEY'] ?? ''),
            self::stringValue($config['LLM_API_BASE_URL'] ?? $config['DEDALUS_API_BASE_URL'] ?? self::defaultBaseUrl($provider)),
            self::stringValue($config['LLM_MODEL'] ?? $config['DEDALUS_MODEL'] ?? self::defaultModel($provider)),
            max(1, (int) ($config['LLM_TIMEOUT_SECONDS'] ?? $config['DEDALUS_TIMEOUT_SECONDS'] ?? 60)),
            self::stringValue($config['LLM_POST_ANALYSIS_PROMPT_PATH'] ?? $config['DEDALUS_POST_ANALYSIS_PROMPT_PATH'] ?? 'prompts/dedalus_post_analysis_system.txt'),
            self::extraHeaders($config['LLM_EXTRA_HEADERS'] ?? []),
        );
    }

    private static function defaultBaseUrl(string $provider): string
    {
        return match (strtolower($provider)) {
            'anthropic' => 'https://api.anthropic.com',
            'openai' => 'https://api.openai.com',
            'openrouter' => 'https://openrouter.ai/api',
            default => 'https://api.dedaluslabs.ai',
        };
    }

    private static function defaultModel(string $provider): string
    {
        return match (strtolower($provider)) {
            'anthropic' => 'claude-3-5-haiku-latest',
            'openai' => 'gpt-5-nano',
            default => 'openai/gpt-5-nano',
        };
    }

    private static function stringValue(mixed $value): string
    {
        return trim((string) $value);
    }

    /**
     * @return array<string, string>
     */
    private static function extraHeaders(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $headers = [];
        foreach ($value as $key => $headerValue) {
            if (!is_string($key)) {
                continue;
            }

            $key = trim($key);
            $headerValue = trim((string) $headerValue);
            if ($key !== '' && $headerValue !== '') {
                $headers[$key] = $headerValue;
            }
        }

        return $headers;
    }
}
