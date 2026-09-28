<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

final class FastScoringRubricRevision
{
    public static function fromConfig(FastScoringConfig $config, string $projectRoot): string
    {
        $path = $config->promptPath;
        $path = str_starts_with($path, '/') ? $path : rtrim($projectRoot, '/\\') . '/' . $path;
        $prompt = @file_get_contents($path);

        return hash('sha256', $prompt === false ? 'missing:' . $path : trim($prompt));
    }
}
