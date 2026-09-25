<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\LlmProviderConfig;
use ForumRewrite\Scoring\FastScoringConfig;
use ForumRewrite\Scoring\FastScoringRubricRevision;

final class FastScoringRubricRevisionTest
{
    public function testRevisionChangesWhenPromptTextChanges(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fast-score-rubric-');
        if ($path === false) {
            throw new RuntimeException('Unable to create rubric fixture.');
        }

        try {
            file_put_contents($path, 'Probability 1 means useful.');
            $config = new FastScoringConfig(true, new LlmProviderConfig('stub', '', '', '', 1, ''), $path);
            $first = FastScoringRubricRevision::fromConfig($config, dirname(__DIR__));
            file_put_contents($path, 'Probability 1 means unrelated.');
            $second = FastScoringRubricRevision::fromConfig($config, dirname(__DIR__));
        } finally {
            @unlink($path);
        }

        assertSame(false, $first === $second);
    }
}
