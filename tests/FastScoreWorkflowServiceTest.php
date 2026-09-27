<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\LlmProviderConfig;
use ForumRewrite\Scoring\DeterministicFastScoreEvaluator;
use ForumRewrite\Scoring\FastScoreContextFactory;
use ForumRewrite\Scoring\FastScoreProvider;
use ForumRewrite\Scoring\FastScoreWorkflowService;
use ForumRewrite\Scoring\FastScoreWorkflowFactory;
use ForumRewrite\Scoring\FastScoringConfig;

final class FastScoreWorkflowServiceTest
{
    public function testDisabledConfigurationDoesNotCallTheScorer(): void
    {
        $scorer = new FastScoreWorkflowFakeProvider();
        $result = $this->service(false, $scorer)->scorePost(['post_id' => 'post-1', 'thread_id' => 'post-1', 'body' => 'Text']);

        assertSame('disabled', $result['status']);
        assertSame(false, $scorer->called);
    }

    public function testDeterministicExclusionSkipsTheModel(): void
    {
        $scorer = new FastScoreWorkflowFakeProvider();
        $result = $this->service(true, $scorer)->scorePost(['post_id' => 'post-1', 'thread_id' => 'post-1', 'body' => '']);

        assertSame('heuristic', $result['source']);
        assertSame(false, $scorer->called);
    }

    public function testConfiguredModelReceivesCompactContext(): void
    {
        $scorer = new FastScoreWorkflowFakeProvider();
        $result = $this->service(true, $scorer)->scorePost(['post_id' => 'post-1', 'thread_id' => 'post-1', 'body' => 'Question?']);

        assertSame('scored', $result['status']);
        assertSame(true, $scorer->called);
        assertSame('Question?', $scorer->context['post_text']);
    }

    public function testLocalPreflightIdentifiesOnlyOutcomesThatNeedNoProviderCall(): void
    {
        $scorer = new FastScoreWorkflowFakeProvider();
        $service = $this->service(true, $scorer);

        assertSame(null, $service->localResultForPost(['post_id' => 'post-1', 'thread_id' => 'post-1', 'body' => 'Question?']));
        assertSame('heuristic', $service->localResultForPost(['post_id' => 'post-2', 'thread_id' => 'post-2', 'body' => ''])['source']);
        assertSame(false, $scorer->called);
    }

    public function testMissingProviderIsAnExplicitNonScoreOutcome(): void
    {
        $result = $this->service(true, null)->scorePost(['post_id' => 'post-1', 'thread_id' => 'post-1', 'body' => 'Question?']);

        assertSame('config_missing', $result['status']);
        assertSame(null, $result['probability']);
    }

    public function testFactoryUsesFastScoringConfigurationWithoutFullAnalysis(): void
    {
        $service = FastScoreWorkflowFactory::fromPrivateConfig([
            'FAST_SCORING_ENABLED' => true,
            'FAST_SCORING_LLM_PROVIDER' => 'stub',
        ], dirname(__DIR__), static fn (string $postId): ?array => null);

        $result = $service->scorePost(['post_id' => 'post-1', 'thread_id' => 'post-1', 'body' => 'Question?']);

        assertSame('config_missing', $result['status']);
    }

    private function service(bool $enabled, ?FastScoreProvider $scorer): FastScoreWorkflowService
    {
        $config = new FastScoringConfig(
            $enabled,
            new LlmProviderConfig('stub', '', '', '', 1, ''),
            '',
        );

        return new FastScoreWorkflowService(
            $config,
            new FastScoreContextFactory(static fn (string $postId): ?array => null),
            new DeterministicFastScoreEvaluator(),
            $scorer,
        );
    }
}

final class FastScoreWorkflowFakeProvider implements FastScoreProvider
{
    public bool $called = false;
    /** @var array<string, mixed> */
    public array $context = [];

    public function score(array $context): array
    {
        $this->called = true;
        $this->context = $context;

        return [
            'status' => 'scored',
            'probability' => 0.7,
            'source' => 'llm',
            'signals' => [],
        ];
    }
}
