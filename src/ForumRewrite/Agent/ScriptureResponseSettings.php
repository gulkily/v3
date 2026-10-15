<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

use ForumRewrite\Llm\LlmProviderConfig;
use InvalidArgumentException;

final class ScriptureResponseSettings
{
    public function __construct(public readonly string $guidance, public readonly ?string $reasoningEffort) {}

    public static function fromConfig(array $config): self
    {
        $guidance = trim((string) ($config['SCRIPTURE_RESPONSE_GUIDANCE'] ?? 'current'));
        $effort = trim((string) ($config['SCRIPTURE_RESPONSE_REASONING_EFFORT'] ?? ''));
        if (!in_array($guidance, ['current', 'revised'], true)) {
            throw new InvalidArgumentException('SCRIPTURE_RESPONSE_GUIDANCE must be current or revised.');
        }
        if ($effort !== '') {
            $provider = LlmProviderConfig::fromPrivateConfig($config);
            if (!in_array($effort, ['minimal', 'low', 'medium', 'high'], true)
                || $provider->provider !== 'openai'
                || preg_match('/^gpt-5(?:-nano|-mini)?(?:-2025-08-07)?$/D', $provider->model) !== 1) {
                throw new InvalidArgumentException('Explicit scripture reasoning effort requires a supported OpenAI GPT-5 model and minimal, low, medium, or high.');
            }
        }
        return new self($guidance, $effort === '' ? null : $effort);
    }
}
