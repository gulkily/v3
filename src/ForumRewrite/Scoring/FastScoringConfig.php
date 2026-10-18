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
        public readonly bool $automaticEnqueue = false,
        public readonly array $rubricIds = ['moderation', 'meta'],
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
                $fallback->provider,
                $fallback->apiKey,
                $fallback->baseUrl,
                (self::stringValue($config['FAST_SCORING_LLM_MODEL'] ?? '') ?: $fallback->model),
                $fallback->timeoutSeconds,
                $fallback->postAnalysisPromptPath,
                $fallback->extraHeaders,
            ),
            self::introductionPath($config['FAST_SCORING_PROMPT_PATH'] ?? 'prompts/fastmod_introduction.txt'),
            self::booleanValue($config['FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED'] ?? false),
            self::rubricIds($config['FAST_SCORING_RUBRICS'] ?? ['moderation', 'meta']),
        );
    }

    private static function introductionPath(mixed $value): string
    {
        $path = self::stringValue($value);
        // Generated configurations previously selected the monolithic JSON rubric.
        return $path === 'prompts/fast_post_scoring_system.txt' ? 'prompts/fastmod_introduction.txt' : $path;
    }

    private static function rubricIds(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || $value === []
            || array_filter($value, static fn ($id) => !is_string($id)) !== []) {
            throw new \InvalidArgumentException('Fastmod requires a nonempty list of rubric identities.');
        }
        return $value;
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
