<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

final class FastScoreWorkflowService
{
    public function __construct(
        private readonly FastScoringConfig $config,
        private readonly FastScoreContextFactory $contextFactory,
        private readonly DeterministicFastScoreEvaluator $deterministicEvaluator,
        private readonly ?FastScoreProvider $scorer,
    ) {
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status:string, probability:?float, source:string, signals:list<string>}
     */
    public function scorePost(array $post): array
    {
        if (!$this->config->enabled) {
            return FastScoreResult::notScored('disabled', ['fast_scoring_disabled']);
        }

        $context = $this->contextFactory->forPost($post);
        $heuristicResult = $this->deterministicEvaluator->evaluate($context);
        if ($heuristicResult !== null) {
            return $heuristicResult;
        }

        if ($this->scorer === null) {
            return FastScoreResult::notScored('config_missing', ['fast_scoring_provider_unavailable']);
        }

        return $this->scorer->score($context);
    }
}
