<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/** Diagnostic identity only: not an authentication credential. */
final class FrontendInstance
{
    public const PATH = '/__v3/status-identity';

    public static function identity(string $projectRoot, string $repositoryRoot, string $databasePath): string
    {
        return hash('sha256', json_encode(array_map(self::canonicalPath(...), [$projectRoot, $repositoryRoot, $databasePath]), JSON_THROW_ON_ERROR));
    }

    private static function canonicalPath(string $path): string
    {
        if (($resolved = realpath($path)) !== false) {
            return $resolved;
        }
        $parent = dirname($path);
        if ($parent === $path) {
            return $path;
        }
        return self::canonicalPath($parent) . '/' . basename($path);
    }
}
