<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

use ForumRewrite\Analysis\ProviderRequestException;
use ForumRewrite\Llm\StructuredChatProvider;

final class FastPostScorer implements FastScoreProvider
{
    public function __construct(
        private readonly StructuredChatProvider $provider,
        private readonly string $systemPrompt,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array{status:string, probability:?float, source:string, signals:list<string>}
     */
    public function score(array $context): array
    {
        try {
            $completion = $this->provider->completeStructuredChat(
                'FastPostScore',
                [
                    ['role' => 'system', 'content' => $this->systemPrompt],
                    ['role' => 'user', 'content' => json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)],
                ],
                self::responseSchema(),
                [
                    'max_completion_tokens' => 128,
                    'exchange_context' => [
                        'call_type' => 'fast_post_score',
                        'post_id' => $context['post_id'] ?? null,
                        'content_hash' => $context['content_hash'] ?? null,
                    ],
                ],
            );
        } catch (ProviderRequestException $error) {
            $failure = FastScoreFailure::fromThrowable($error);
            return FastScoreResult::notScored('provider_error', ['provider_error'], $failure['failure_code']);
        } catch (\Throwable) {
            return FastScoreResult::notScored('invalid_response', ['invalid_response'], 'invalid_response');
        }

        $probability = $completion['decoded']['probability'] ?? null;
        if ((!is_int($probability) && !is_float($probability))
            || !is_finite((float) $probability)
            || $probability < 0 || $probability > 1) {
            return FastScoreResult::notScored('invalid_response', ['invalid_probability'], 'invalid_response');
        }

        return FastScoreResult::scored((float) $probability, 'llm');
    }

    /** @return array<string, mixed> */
    private static function responseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'probability' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                ],
            ],
            'required' => ['probability'],
        ];
    }
}
