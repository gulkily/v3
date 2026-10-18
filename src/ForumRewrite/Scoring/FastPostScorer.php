<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

use ForumRewrite\Analysis\ProviderRequestException;
use ForumRewrite\Llm\TextChatProvider;

final class FastPostScorer implements FastScoreProvider
{
    public function __construct(
        private readonly TextChatProvider $provider,
        private readonly string $systemPrompt,
        private readonly array $rubricIds = ['moderation'],
        private readonly ?string $rubricRevision = null,
    ) {
    }

    public function rubricRevision(): ?string
    {
        return $this->rubricRevision;
    }

    /**
     * @param array<string, mixed> $context
     * @return array{status:string, probability:?float, source:string, signals:list<string>}
     */
    public function score(array $context): array
    {
        $dependencies = $context['exchange_dependencies'] ?? \ForumRewrite\Llm\ExchangeDependencies::manifest([], false);
        unset($context['exchange_dependencies']);
        try {
            $completion = $this->provider->completeTextChat(
                [
                    ['role' => 'system', 'content' => $this->systemPrompt],
                    ['role' => 'user', 'content' => json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)],
                ],
                [
                    'max_completion_tokens' => 1024,
                    'exchange_context' => [
                        'call_type' => 'fast_post_score',
                        'post_id' => $context['post_id'] ?? null,
                        'content_hash' => $context['content_hash'] ?? null,
                        'exchange_dependencies' => $dependencies,
                    ],
                ],
            );
        } catch (ProviderRequestException $error) {
            $failure = FastScoreFailure::fromThrowable($error);
            return FastScoreResult::notScored('provider_error', ['provider_error'], $failure['failure_code']);
        } catch (\Throwable) {
            return FastScoreResult::notScored('invalid_response', ['invalid_response'], 'invalid_response');
        }

        try {
            $scores = self::parseCsv((string) ($completion['response_text'] ?? ''), $this->rubricIds);
        } catch (\InvalidArgumentException) {
            return FastScoreResult::notScored('invalid_response', ['invalid_scores'], 'invalid_response');
        }
        $result = FastScoreResult::scoredSet($scores, 'llm');
        $result['rubric_revision'] = $this->rubricRevision;
        return $result;
    }

    /** @param list<string> $ids @return array<string,float> */
    public static function parseCsv(string $text, array $ids): array
    {
        $fields = explode(',', trim($text));
        if ($ids === [] || count(array_unique($ids)) !== count($ids) || count($fields) !== count($ids)) {
            throw new \InvalidArgumentException('Invalid score count.');
        }
        $scores = [];
        foreach ($fields as $index => $field) {
            $field = trim($field);
            if (!preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?$/D', $field)) {
                throw new \InvalidArgumentException('Invalid numeric score.');
            }
            $value = (float) $field;
            if (!is_finite($value) || $value < 0 || $value > 1) {
                throw new \InvalidArgumentException('Score outside probability range.');
            }
            $scores[$ids[$index]] = $value;
        }
        return $scores;
    }
}
