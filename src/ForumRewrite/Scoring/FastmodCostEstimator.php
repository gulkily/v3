<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

use PDO;

final class FastmodCostEstimator
{
    private const FALLBACK_INPUT_TOKENS = 2500;
    private const FALLBACK_OUTPUT_TOKENS = 1024;

    public function __construct(
        private readonly PDO $exchangePdo,
        private readonly string $model,
        private readonly float $inputUsdPerMillion,
        private readonly float $outputUsdPerMillion,
    ) {
        $this->exchangePdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * @param array{counts:array<string, int>} $audit
     * @return array{candidate_count:int,sample_count:int,input_tokens_per_post:float,output_tokens_per_post:float,estimated_cost_usd:float,assumption:string}
     */
    public function estimate(array $audit): array
    {
        $samples = $this->usageSamples();
        $sampleCount = count($samples);
        $inputTokens = $sampleCount === 0
            ? (float) self::FALLBACK_INPUT_TOKENS
            : array_sum(array_column($samples, 'input')) / $sampleCount;
        $outputTokens = $sampleCount === 0
            ? (float) self::FALLBACK_OUTPUT_TOKENS
            : array_sum(array_column($samples, 'output')) / $sampleCount;
        $candidateCount = (int) ($audit['counts']['unrated'] ?? 0);
        $estimatedCost = $candidateCount * (($inputTokens * $this->inputUsdPerMillion + $outputTokens * $this->outputUsdPerMillion) / 1000000);

        return [
            'candidate_count' => $candidateCount,
            'sample_count' => $sampleCount,
            'input_tokens_per_post' => $inputTokens,
            'output_tokens_per_post' => $outputTokens,
            'estimated_cost_usd' => $estimatedCost,
            'assumption' => $sampleCount === 0 ? 'conservative_fallback' : 'observed_fastmod_usage',
        ];
    }

    /** @return list<array{input:float,output:float}> */
    private function usageSamples(): array
    {
        $stmt = $this->exchangePdo->prepare("SELECT response_json FROM llm_exchanges WHERE call_type = 'fast_post_score' AND provider_model = :model AND status = 'completed'");
        $stmt->execute(['model' => $this->model]);
        $samples = [];
        foreach ($stmt->fetchAll() as $row) {
            $response = json_decode((string) $row['response_json'], true);
            $decoded = is_array($response) ? ($response['decoded'] ?? $response['response']['decoded'] ?? null) : null;
            $usage = is_array($decoded) ? ($decoded['usage'] ?? null) : null;
            $input = is_array($usage) ? ($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? null) : null;
            $output = is_array($usage) ? ($usage['completion_tokens'] ?? $usage['output_tokens'] ?? null) : null;
            if (is_numeric($input) && is_numeric($output)) {
                $samples[] = ['input' => (float) $input, 'output' => (float) $output];
            }
        }
        return $samples;
    }
}
