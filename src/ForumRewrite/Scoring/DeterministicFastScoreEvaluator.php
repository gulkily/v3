<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

final class DeterministicFastScoreEvaluator
{
    /**
     * @param array<string, mixed> $context
     * @return array{status:string, probability:?float, source:string, signals:list<string>}|null
     */
    public function evaluate(array $context): ?array
    {
        if (trim((string) ($context['post_text'] ?? '')) === '') {
            return FastScoreResult::excluded(['empty_post']);
        }

        return null;
    }
}
