<?php

declare(strict_types=1);

namespace ForumRewrite\Offline;

/**
 * Finds the snapshot the host actually serves, using the same precedence as
 * the front controller: the static HTML root first, then the active release.
 */
final class OfflineSnapshotLocator
{
    public function servedSnapshotPath(string $staticHtmlRoot): ?string
    {
        if ($staticHtmlRoot === '') {
            return null;
        }

        $candidates = [rtrim($staticHtmlRoot, '/') . '/offline/snapshot.sqlite3'];
        $releaseRoot = $this->activeStaticReleaseRoot($staticHtmlRoot);
        if ($releaseRoot !== null) {
            $candidates[] = $releaseRoot . '/offline/snapshot.sqlite3';
        }

        foreach ($candidates as $path) {
            if ($this->isSqliteFile($path)) {
                return $path;
            }
        }

        return null;
    }

    public function servedUpdatePath(string $staticHtmlRoot): ?string
    {
        if ($staticHtmlRoot === '') {
            return null;
        }

        $candidates = [rtrim($staticHtmlRoot, '/') . '/offline/update.sqlite3'];
        $releaseRoot = $this->activeStaticReleaseRoot($staticHtmlRoot);
        if ($releaseRoot !== null) {
            $candidates[] = $releaseRoot . '/offline/update.sqlite3';
        }
        foreach ($candidates as $path) {
            if ($this->isSqliteFile($path)) {
                return $path;
            }
        }

        return null;
    }

    /** SHA-256 from the manifest beside the served snapshot, or '' when unavailable. */
    public function manifestRevision(string $staticHtmlRoot): string
    {
        $snapshotPath = $this->servedSnapshotPath($staticHtmlRoot);
        if ($snapshotPath === null) {
            return '';
        }

        $manifestPath = dirname($snapshotPath) . '/manifest.json';
        if (!is_file($manifestPath)) {
            return '';
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);
        $sha256 = is_array($decoded) ? ($decoded['sha256'] ?? null) : null;

        return is_string($sha256) && preg_match('/^[a-f0-9]{64}$/', $sha256) === 1 ? $sha256 : '';
    }

    private function activeStaticReleaseRoot(string $staticHtmlRoot): ?string
    {
        $currentPath = rtrim($staticHtmlRoot, '/') . '/current';
        clearstatcache(true, $currentPath);
        $releaseRoot = realpath($currentPath);

        return $releaseRoot !== false && is_dir($releaseRoot) ? $releaseRoot : null;
    }

    private function isSqliteFile(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        try {
            return fread($handle, 16) === "SQLite format 3\000";
        } finally {
            fclose($handle);
        }
    }
}
