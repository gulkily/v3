<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Scoring\FastmodCostEstimator;

final class FastmodCostEstimatorTest
{
    public function testUsesObservedFastmodUsageForTheConfiguredModel(): void
    {
        $pdo = $this->database();
        $this->insert($pdo, 'gpt-5-nano', 400, 100);
        $this->insert($pdo, 'gpt-5-nano', 600, 300);
        $this->insert($pdo, 'other', 999, 999);

        $estimate = (new FastmodCostEstimator($pdo, 'gpt-5-nano', 0.05, 0.40))->estimate(['counts' => ['unrated' => 10]]);

        assertSame(2, $estimate['sample_count']);
        assertSame(500.0, $estimate['input_tokens_per_post']);
        assertSame(200.0, $estimate['output_tokens_per_post']);
        assertSame('observed_fastmod_usage', $estimate['assumption']);
        assertSame(0.00105, round($estimate['estimated_cost_usd'], 5));
    }

    public function testUsesConservativeFallbackWithoutUsageHistory(): void
    {
        $estimate = (new FastmodCostEstimator($this->database(), 'gpt-5-nano', 0.05, 0.40))->estimate(['counts' => ['unrated' => 1]]);

        assertSame(0, $estimate['sample_count']);
        assertSame(2500.0, $estimate['input_tokens_per_post']);
        assertSame(1024.0, $estimate['output_tokens_per_post']);
        assertSame('conservative_fallback', $estimate['assumption']);
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE llm_exchanges (call_type TEXT, provider_model TEXT, status TEXT, response_json TEXT)');
        return $pdo;
    }

    private function insert(PDO $pdo, string $model, int $input, int $output): void
    {
        $stmt = $pdo->prepare('INSERT INTO llm_exchanges (call_type, provider_model, status, response_json) VALUES ("fast_post_score", :model, "completed", :response)');
        $stmt->execute(['model' => $model, 'response' => json_encode(['response' => ['decoded' => ['usage' => ['prompt_tokens' => $input, 'completion_tokens' => $output]]]])]);
    }
}
