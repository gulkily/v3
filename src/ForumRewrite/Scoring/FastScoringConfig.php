<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

use ForumRewrite\Llm\LlmProviderConfig;

final class FastScoringConfig
{
    public function __construct(
        public readonly bool $enabled,
        public readonly LlmProviderConfig $provider,
        public readonly string $promptPath,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromPrivateConfig(array $config): self
    {
        $fallback = LlmProviderConfig::fromPrivateConfig($config);

        return new self(
            self::booleanValue($config['FAST_SCORING_ENABLED'] ?? false),
            new LlmProviderConfig(
                self::stringValue($config['FAST_SCORING_LLM_PROVIDER'] ?? $fallback->provider),
                self::stringValue($config['FAST_SCORING_LLM_API_KEY'] ?? $fallback->apiKey),
                self::stringValue($config['FAST_SCORING_LLM_API_BASE_URL'] ?? $fallback->baseUrl),
                self::stringValue($config['FAST_SCORING_LLM_MODEL'] ?? $fallback->model),
                max(1, (int) ($config['FAST_SCORING_LLM_TIMEOUT_SECONDS'] ?? $fallback->timeoutSeconds)),
                self::stringValue($config['FAST_SCORING_PROMPT_PATH'] ?? 'prompts/fast_post_scoring_system.txt'),
                self::headers($config['FAST_SCORING_LLM_EXTRA_HEADERS'] ?? $fallback->extraHeaders),
            ),
            self::stringValue($config['FAST_SCORING_PROMPT_PATH'] ?? 'prompts/fast_post_scoring_system.txt'),
        );
    }

    private static function booleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value !== 0.0;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function stringValue(mixed $value): string
    {
        return trim((string) $value);
    }

    /**
     * @return array<string, string>
     */
    private static function headers(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $headers = [];
        foreach ($value as $name => $headerValue) {
            if (!is_string($name)) {
                continue;
            }

            $name = trim($name);
            $headerValue = trim((string) $headerValue);
            if ($name !== '' && $headerValue !== '') {
                $headers[$name] = $headerValue;
            }
        }

        return $headers;
    }
}
