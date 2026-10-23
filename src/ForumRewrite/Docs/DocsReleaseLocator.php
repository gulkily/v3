<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

final class DocsReleaseLocator
{
    public const METADATA = '.docs-release.json';
    public const LEGACY = '.docs-legacy.json';

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
        return $this->native() ?? $this->legacy();
    }

    private function native(): ?array
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

    /** Bounded metadata/file checks; never discovers or validates the documentation inventory. */
    public function legacy(): ?array
    {
        $base = realpath($this->staticHtmlRoot);
        if ($base === false || is_link($this->staticHtmlRoot)) {
            return null;
        }
        $record = self::file($base, self::LEGACY);
        if ($record !== null && filesize($record) < 4096) {
            $data = json_decode((string) file_get_contents($record), true);
            $relative = is_array($data) && ($data['format'] ?? null) === 1 ? ($data['relative'] ?? null) : null;
            if (is_string($relative) && ($relative === '.' ||
                (!str_contains($relative, '\\') && !str_contains($relative, "\0")
                    && array_intersect(explode('/', $relative), ['', '.', '..']) === []))) {
                $path = $relative === '.' ? $base : $base . '/' . $relative;
                $release = $this->legacyAt($base, $path);
                if ($release !== null) {
                    return $release;
                }
            }
        }
        clearstatcache(true, $base . '/current');
        $current = is_link($base . '/current') ? realpath($base . '/current') : false;
        return ($current === false ? null : $this->legacyAt($base, $current)) ?? $this->legacyAt($base, $base);
    }

    private function legacyAt(string $base, string $path): ?array
    {
        if (($path !== $base && !str_starts_with($path, $base . '/')) || realpath($path) !== $path
            || is_link($path) || realpath($path . '/docs') !== $path . '/docs' || is_link($path . '/docs.html')
            || self::file($path, 'docs/index.html') === null || self::file($path, 'docs/_search.json') === null) {
            return null;
        }
        return ['root' => $path, 'version' => hash('sha256', 'legacy-docs:' . $path), 'legacy' => true, 'documents' => 0];
    }

    public function preserveLegacy(): void
    {
        $legacy = $this->legacy();
        if ($legacy !== null) {
            $this->pinLegacyRelease($legacy['root']);
        }
    }

    public function pinLegacyRelease(string $releaseRoot): void
    {
        $base = realpath($this->staticHtmlRoot);
        if ($base === false || is_link($this->staticHtmlRoot) || $this->legacyAt($base, $releaseRoot) === null) {
            return;
        }
        $existing = self::file($base, self::LEGACY);
        if ($existing !== null && filesize($existing) < 4096) {
            $record = json_decode((string) file_get_contents($existing), true);
            $relative = $releaseRoot === $base ? '.' : substr($releaseRoot, strlen($base) + 1);
            if (($record['relative'] ?? null) === $relative) {
                return;
            }
        }
        $temporary = @tempnam($base, '.legacy-docs-');
        if ($temporary === false) {
            error_log('Cannot retain legacy docs reference; run ./v3 docs publish with the same destination/profile.');
            return;
        }
        try {
            $data = json_encode(['format' => 1, 'relative' => $releaseRoot === $base ? '.' : substr($releaseRoot, strlen($base) + 1)], JSON_THROW_ON_ERROR);
            if (file_put_contents($temporary, $data) !== strlen($data) || !rename($temporary, $base . '/' . self::LEGACY)) {
                error_log('Cannot retain legacy docs reference; run ./v3 docs publish with the same destination/profile.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public static function ownsLegacyPath(string $legacyRoot, string $path): bool
    {
        return $path === $legacyRoot . '/docs' || str_starts_with($path, $legacyRoot . '/docs/')
            || $path === $legacyRoot . '/docs.html';
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
