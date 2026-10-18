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

    public function save(array $ids, FastScoringConfig $config): array
    {
        $composition = (new FastmodRubrics($this->projectRoot, $config->promptPath))->compose($ids);
        $directory = dirname($this->path());
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create private Fastmod settings directory.');
        }
        $temporary = tempnam($directory, '.fastmod-');
        if ($temporary === false) {
            throw new \RuntimeException('Cannot save Fastmod settings.');
        }
        try {
            chmod($temporary, 0600);
            if (file_put_contents($temporary, json_encode($composition['ids'], JSON_THROW_ON_ERROR) . "\n") === false
                || !rename($temporary, $this->path())) {
                throw new \RuntimeException('Cannot save Fastmod settings.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
        return $composition;
    }

    public function composition(FastScoringConfig $config): array
    {
        return (new FastmodRubrics($this->projectRoot, $config->promptPath))->compose($this->enabled($config));
    }
}
