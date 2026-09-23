<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/**
 * Validates and normalizes canonical-record source paths and commit SHAs
 * used by the /source/* routes, the activity feed's source links, and the
 * account page's public-key link - pure string/pattern matching plus one
 * filesystem existence check, no other dependencies. Widely shared (4-5
 * call sites each for the two validators at the time of extraction), so
 * pulled out rather than duplicated. See
 * docs/plans/codebase_cleanup_audit_findings_v1.md, Phase 2.
 */
final class SourcePathValidator
{
    public static function normalizeRoutePath(string $encodedRelativePath): ?string
    {
        $relativePath = rawurldecode($encodedRelativePath);
        $relativePath = str_replace('\\', '/', $relativePath);
        if ($relativePath === '' || str_starts_with($relativePath, '/')) {
            return null;
        }

        foreach (explode('/', $relativePath) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $relativePath;
    }

    public static function isValidCanonicalPath(string $relativePath): bool
    {
        return self::isValidCanonicalRecordPath($relativePath)
            || self::isValidCanonicalDetachedSignaturePath($relativePath);
    }

    public static function isValidCanonicalRecordPath(string $relativePath): bool
    {
        if (!str_starts_with($relativePath, 'records/')) {
            return false;
        }

        return $relativePath === 'records/instance/public.txt'
            || $relativePath === 'records/instance/feature-flags.txt'
            || preg_match('#^records/posts/(?:\d{4}/\d{2}/\d{2}/)?[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
            || preg_match('#^records/thread-labels/[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
            || preg_match('#^records/post-reactions/[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
            || preg_match('#^records/identity/identity-openpgp-[A-Fa-f0-9]{40}\.txt$#', $relativePath) === 1
            || preg_match('#^records/approval-seeds/openpgp-[A-Fa-f0-9]{40}\.txt$#', $relativePath) === 1
            || preg_match('#^records/public-keys/openpgp-[A-Fa-f0-9]{40}\.asc$#', $relativePath) === 1;
    }

    public static function isValidCanonicalDetachedSignaturePath(string $relativePath): bool
    {
        foreach (['.asc', '.sig'] as $suffix) {
            if (str_ends_with($relativePath, $suffix)) {
                return self::isValidCanonicalRecordPath(substr($relativePath, 0, -strlen($suffix)));
            }
        }

        return false;
    }

    public static function isValidCommitSha(string $commitSha): bool
    {
        return preg_match('/^[A-Fa-f0-9]{40}$/', $commitSha) === 1;
    }

    public static function currentPathExists(string $repositoryRoot, string $relativePath): bool
    {
        $resolvedRoot = realpath($repositoryRoot);
        if ($resolvedRoot === false) {
            return false;
        }

        $path = $repositoryRoot . '/' . $relativePath;
        $realPath = realpath($path);
        if ($realPath === false || !is_file($realPath)) {
            return false;
        }

        $rootPrefix = rtrim($resolvedRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($realPath, $rootPrefix);
    }
}
