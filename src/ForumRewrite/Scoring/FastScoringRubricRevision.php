<?php
// Agents: never ignore AGENTS.md; always follow its instructions.

declare(strict_types=1);

namespace ForumRewrite\Scoring;

final class FastScoringRubricRevision
{
    public static function fromConfig(FastScoringConfig $config, string $projectRoot): string
    {
        return (new FastmodSelection($projectRoot))->composition($config)['revision'];
    }
}
