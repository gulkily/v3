<?php

declare(strict_types=1);

namespace ForumRewrite\Llm;

use RuntimeException;

final class LlmProviderConfig
{
    private const PLACEHOLDER_API_KEY = 'replace-with-real-key';

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
        $provider = strtolower(self::stringValue($config['LLM_PROVIDER'] ?? null));
        $apiKey = self::stringValue($config['LLM_API_KEY'] ?? $config['DEDALUS_API_KEY'] ?? '');
        if ($provider === '') {
            if ($legacyMode === 'stub') {
                $provider = 'stub';
            } elseif ($apiKey !== '' && $apiKey !== self::PLACEHOLDER_API_KEY) {
                // A real API key with no provider is ambiguous now that there is no default
                // provider to fall back to; an unset provider with a blank or placeholder key
                // is the normal "not configured yet" state and should not throw.
                throw new RuntimeException(
                    'LLM_PROVIDER must be set in the private config (e.g. "anthropic", "openai", "openrouter", or a custom OpenAI-compatible gateway name). There is no default provider.'
                );
            }
        }

        return new self(
            $provider,
            $apiKey,
            $provider === '' ? '' : self::stringValue($config['LLM_API_BASE_URL'] ?? $config['DEDALUS_API_BASE_URL'] ?? self::defaultBaseUrl($provider)),
            $provider === '' ? '' : self::stringValue($config['LLM_MODEL'] ?? $config['DEDALUS_MODEL'] ?? self::defaultModel($provider)),
            max(1, (int) ($config['LLM_TIMEOUT_SECONDS'] ?? $config['DEDALUS_TIMEOUT_SECONDS'] ?? 60)),
            self::stringValue($config['LLM_POST_ANALYSIS_PROMPT_PATH'] ?? $config['DEDALUS_POST_ANALYSIS_PROMPT_PATH'] ?? 'prompts/dedalus_post_analysis_system.txt'),
            self::extraHeaders($config['LLM_EXTRA_HEADERS'] ?? []),
        );
    }

    private static function defaultBaseUrl(string $provider): string
    {
        return match ($provider) {
            'anthropic' => 'https://api.anthropic.com',
            'openai' => 'https://api.openai.com',
            'openrouter' => 'https://openrouter.ai/api',
            'stub' => '',
            default => throw new RuntimeException(
                "LLM_API_BASE_URL must be set in the private config for provider \"{$provider}\"."
            ),
        };
    }

    private static function defaultModel(string $provider): string
    {
        return match ($provider) {
            'anthropic' => 'claude-haiku-4-5-20251001',
            'openai' => 'gpt-5-nano',
            'openrouter' => 'openai/gpt-5-nano',
            'stub' => '',
            default => throw new RuntimeException(
                "LLM_MODEL must be set in the private config for provider \"{$provider}\"."
            ),
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
