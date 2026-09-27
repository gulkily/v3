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
        $localResult = $this->localResultForPost($post);
        if ($localResult !== null) {
            return $localResult;
        }

        return $this->scorer?->score($this->contextFactory->forPost($post))
            ?? throw new \LogicException('A provider result was required but no provider is configured.');
    }

    /**
     * Returns a terminal result that does not require a provider request, or
     * null when the configured provider must be called.
     *
     * @param array<string, mixed> $post
     * @return array{status:string, probability:?float, source:string, signals:list<string>}|null
     */
    public function localResultForPost(array $post): ?array
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
            return FastScoreResult::notScored('config_missing', ['fast_scoring_provider_unavailable'], 'config_missing');
        }

        return null;
    }
}
