<?php

declare(strict_types=1);

/**
 * Record-file helpers shared by the operator scripts (archive_thread,
 * delete_record, thread_attributes, import_repository_archive,
 * audit_post_signatures). Plain functions, so scripts keep calling them by
 * the same names; moved here unchanged from those scripts.
 */

use ForumRewrite\Canonical\CanonicalPathResolver;

function repositoryRelativePath(string $repositoryRoot, string $path): string
{
    $prefix = rtrim($repositoryRoot, '/') . '/';
    if (!str_starts_with($path, $prefix)) {
        throw new RuntimeException('Path is outside repository root: ' . $path);
    }

    return substr($path, strlen($prefix));
}

function assertCanonicalRecordPath(string $relativePath): void
{
    if (!str_starts_with($relativePath, 'records/')) {
        fwrite(STDERR, "Record path must be under records/: {$relativePath}\n");
        exit(1);
    }

    if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/') || str_ends_with($relativePath, '/')) {
        fwrite(STDERR, "Invalid record path: {$relativePath}\n");
        exit(1);
    }

    if (!preg_match('/\.(txt|asc)$/', $relativePath)) {
        fwrite(STDERR, "Record path must end in .txt or .asc: {$relativePath}\n");
        exit(1);
    }
}

/**
 * @return list<string>
 */
function recordFiles(string $repositoryRoot): array
{
    $recordsRoot = $repositoryRoot . '/records';
    if (!is_dir($recordsRoot)) {
        return [];
    }

    $paths = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($recordsRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $path = $item->getPathname();
        if (!preg_match('/\.(txt|asc)$/', $path)) {
            continue;
        }

        $paths[] = repositoryRelativePath($repositoryRoot, $path);
    }
    sort($paths);

    return $paths;
}

/**
 * @return list<string>
 */
function postRecordPaths(string $repositoryRoot): array
{
    $paths = [];
    $postsRoot = $repositoryRoot . '/records/posts';
    if (!is_dir($postsRoot)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($postsRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if (!$item->isFile() || !str_ends_with($item->getFilename(), '.txt')) {
            continue;
        }

        $paths[] = repositoryRelativePath($repositoryRoot, $item->getPathname());
    }

    sort($paths);

    return $paths;
}

function existingPostPath(string $repositoryRoot, string $postId): ?string
{
    foreach (CanonicalPathResolver::postCandidates($postId) as $relativePath) {
        if (is_file($repositoryRoot . '/' . $relativePath)) {
            return $relativePath;
        }
    }

    foreach (postRecordPaths($repositoryRoot) as $relativePath) {
        if (basename($relativePath) === $postId . '.txt') {
            return $relativePath;
        }
    }

    return null;
}
