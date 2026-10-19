<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

use ForumRewrite\Llm\LlmProviderConfig;
use InvalidArgumentException;

final class ScriptureResponseSettings
{
    public function __construct(public readonly string $guidance, public readonly ?string $reasoningEffort, public readonly ?string $writerModel = null) {}

    public static function fromConfig(array $config): self
    {
        $guidance = trim((string) ($config['SCRIPTURE_RESPONSE_GUIDANCE'] ?? 'current'));
        $effort = trim((string) ($config['SCRIPTURE_RESPONSE_REASONING_EFFORT'] ?? ''));
        if (!in_array($guidance, ['current', 'revised', 'verified'], true)) {
            throw new InvalidArgumentException('SCRIPTURE_RESPONSE_GUIDANCE must be current, revised, or verified.');
        }
        $writer = trim((string) ($config['SCRIPTURE_RESPONSE_MODEL'] ?? ''));
        if ($writer !== '') {
            $provider = LlmProviderConfig::fromPrivateConfig($config);
            if ($provider->provider !== 'openai' || !in_array($writer, ['gpt-5-mini', 'gpt-5.6-sol'], true)) {
                throw new InvalidArgumentException('SCRIPTURE_RESPONSE_MODEL requires OpenAI and gpt-5-mini or gpt-5.6-sol.');
            }
            $config['LLM_MODEL'] = $writer;
        }
        if ($effort !== '') {
            $provider = LlmProviderConfig::fromPrivateConfig($config);
            if (!in_array($effort, $provider->model === 'gpt-5.6-sol' ? ['low'] : ['minimal', 'low', 'medium', 'high'], true)
                || $provider->provider !== 'openai'
                || preg_match('/^(?:gpt-5(?:-nano|-mini)?(?:-2025-08-07)?|gpt-5\.6-sol)$/D', $provider->model) !== 1) {
                throw new InvalidArgumentException('Explicit scripture reasoning effort requires a supported OpenAI GPT-5 model and minimal, low, medium, or high.');
            }
        }
        return new self($guidance, $effort === '' ? null : $effort, $writer === '' ? null : $writer);
    }
}
