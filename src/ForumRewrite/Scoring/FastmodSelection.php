<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

final class FastmodSelection
{
    public function __construct(private readonly string $projectRoot) {}

    public function path(): string
    {
        return $this->projectRoot . '/state/private/fastmod_rubrics.json';
    }

    public function enabled(FastScoringConfig $config): array
    {
        if (!file_exists($this->path())) {
            return $config->rubricIds;
        }
        $text = @file_get_contents($this->path());
        if ($text === false) {
            throw new \RuntimeException('Cannot read Fastmod rubric selection.');
        }
        try {
            $ids = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException('Invalid Fastmod rubric selection.', 0, $error);
        }
        if (!is_array($ids) || !array_is_list($ids)) {
            throw new \RuntimeException('Invalid Fastmod rubric selection.');
        }
        return $ids;
    }

    public function composition(FastScoringConfig $config): array
    {
        return (new FastmodRubrics($this->projectRoot, $config->promptPath))->compose($this->enabled($config));
    }
}
