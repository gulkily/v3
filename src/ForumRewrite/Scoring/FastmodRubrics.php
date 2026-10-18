<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

final class FastmodRubrics
{
    public function __construct(private readonly string $projectRoot, private readonly string $introductionPath = 'prompts/fastmod_introduction.txt') {}

    /** @return array<string, string> */
    public function catalog(): array
    {
        $catalog = [];
        foreach (glob($this->projectRoot . '/prompts/fastmod/*.txt') ?: [] as $path) {
            $id = basename($path, '.txt');
            if (!preg_match('/^[a-z][a-z0-9_]*$/D', $id)) {
                throw new \RuntimeException('Invalid Fastmod rubric identity.');
            }
            $catalog[$id] = $path;
        }
        ksort($catalog, SORT_STRING);
        return $catalog;
    }

    /** @param list<string> $enabledIds
     * @return array{prompt:string, ids:list<string>, revision:string}
     */
    public function compose(array $enabledIds): array
    {
        if ($enabledIds === [] || count(array_unique($enabledIds)) !== count($enabledIds)) {
            throw new \InvalidArgumentException('Select at least one distinct Fastmod rubric.');
        }
        $catalog = $this->catalog();
        foreach ($enabledIds as $id) {
            if (!is_string($id) || !isset($catalog[$id])) {
                throw new \InvalidArgumentException('Unknown or missing Fastmod rubric.');
            }
        }
        sort($enabledIds, SORT_STRING);
        $parts = [$this->read(str_starts_with($this->introductionPath, '/') ? $this->introductionPath : $this->projectRoot . '/' . $this->introductionPath)];
        foreach ($enabledIds as $id) {
            $parts[] = $id . ': ' . $this->read($catalog[$id]);
        }
        $prompt = implode("\n\n", $parts);
        return ['prompt' => $prompt, 'ids' => $enabledIds,
            'revision' => hash('sha256', json_encode([$enabledIds, $prompt], JSON_THROW_ON_ERROR))];
    }

    private function read(string $path): string
    {
        $text = @file_get_contents($path);
        if ($text === false || trim($text) === '') {
            throw new \RuntimeException('Fastmod template is missing or empty: ' . basename($path));
        }
        return trim($text);
    }
}
