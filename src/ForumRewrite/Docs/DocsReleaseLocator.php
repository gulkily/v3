<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

final class DocsReleaseLocator
{
    public const METADATA = '.docs-release.json';

    public function __construct(private readonly string $staticHtmlRoot)
    {
        if (rtrim($staticHtmlRoot, '/') === '' || str_contains($staticHtmlRoot, "\0")) {
            throw new \InvalidArgumentException('Select a non-root forum static directory for docs publication.');
        }
    }

    /** Sibling storage is outside forum invalidation/sweep traversal. */
    public function root(): string
    {
        return rtrim($this->staticHtmlRoot, '/') . '-docs';
    }

    public function active(): ?array
    {
        $root = $this->root();
        clearstatcache(true, $root . '/current');
        if (is_link($root) || is_link($root . '/releases') || !is_link($root . '/current')) {
            return null;
        }
        $release = realpath($root . '/current');
        $releases = realpath($root . '/releases');
        if ($release === false || $releases === false || dirname($release) !== $releases) {
            return null;
        }
        $metadata = self::file($release, self::METADATA);
        if ($metadata === null || filesize($metadata) > 4096) {
            return null;
        }
        $record = json_decode((string) file_get_contents($metadata), true);
        if (!is_array($record) || ($record['format'] ?? null) !== 1
            || !is_string($record['version'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $record['version'])) {
            return null;
        }
        return ['root' => $release, 'version' => $record['version'], 'legacy' => false,
            'documents' => (int) ($record['documents'] ?? 0)];
    }

    public static function file(string $root, string $relative): ?string
    {
        if (str_contains($relative, "\0") || str_contains($relative, '\\')
            || array_intersect(explode('/', $relative), ['', '.', '..']) !== []) {
            return null;
        }
        $base = realpath($root);
        if ($base === false || is_link($root)) {
            return null;
        }
        $path = $base . '/' . $relative;
        return realpath($path) === $path && is_file($path) && !is_link($path) ? $path : null;
    }
}
