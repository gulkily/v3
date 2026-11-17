<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use RuntimeException;

/**
 * Sources the operator imported from, newest first, for the Tools → Import page.
 *
 * Kept as a private plain-text file of blank-line-separated `Source:` / `Last-Used:` blocks
 * beside the import requests, outside the content repository and public downloads.
 */
final class InstanceImportSources
{
    public const LIMIT = 20;

    public function __construct(private readonly string $stateDirectory)
    {
    }

    /** @return list<array{source: string, last_used: string}> */
    public function all(): array
    {
        $path = $this->path();
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }
        $sources = [];
        foreach (preg_split('/\n\s*\n/', (string) file_get_contents($path)) ?: [] as $block) {
            $fields = InstanceImportTask::parse($block);
            if (($fields['Source'] ?? '') !== '') {
                $sources[] = ['source' => $fields['Source'], 'last_used' => $fields['Last-Used'] ?? ''];
            }
        }

        return array_slice($sources, 0, self::LIMIT);
    }

    public function remember(string $source, ?string $usedAt = null): void
    {
        $source = trim($source);
        if ($source === '' || preg_match('/[\x00-\x1F\x7F]/', $source) === 1) {
            return;
        }
        $sources = array_values(array_filter($this->all(), static fn (array $entry): bool => $entry['source'] !== $source));
        array_unshift($sources, ['source' => $source, 'last_used' => $usedAt ?? gmdate('Y-m-d\TH:i:s\Z')]);
        $text = '';
        foreach (array_slice($sources, 0, self::LIMIT) as $entry) {
            $text .= 'Source: ' . $entry['source'] . "\nLast-Used: " . $entry['last_used'] . "\n\n";
        }
        if (!is_dir($this->stateDirectory) && !mkdir($this->stateDirectory, 0700, true) && !is_dir($this->stateDirectory)) {
            throw new RuntimeException('Import state directory is not writable.');
        }
        $temporary = $this->path() . '.tmp';
        if (file_put_contents($temporary, $text) !== strlen($text) || !chmod($temporary, 0600) || !rename($temporary, $this->path())) {
            throw new RuntimeException('Unable to save remembered import sources.');
        }
    }

    private function path(): string
    {
        return $this->stateDirectory . '/sources.txt';
    }
}
