<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Scoring\DeterministicFastScoreEvaluator;

final class DeterministicFastScoreEvaluatorTest
{
    public function testEmptyPostIsExcludedWithHeuristicSource(): void
    {
        $result = (new DeterministicFastScoreEvaluator())->evaluate(['post_text' => " \n "]);

        assertSame([
            'status' => 'excluded',
            'probability' => null,
            'source' => 'heuristic',
            'signals' => ['empty_post'],
        ], $result);
    }

    public function testNonEmptyPostIsLeftForTheConfiguredRubric(): void
    {
        assertSame(null, (new DeterministicFastScoreEvaluator())->evaluate(['post_text' => 'A question?']));
    }
}
