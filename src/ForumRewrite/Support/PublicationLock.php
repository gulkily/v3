<?php

declare(strict_types=1);
namespace ForumRewrite\Support;

/** Serialize publication and access-mode changes; no retraction freshness policy. */
final class PublicationLock
{
    private static array $held = [];
    public static function lock(string $repositoryRoot): ExecutionLock
    {
        $root = realpath($repositoryRoot) ?: $repositoryRoot;
        $path = is_dir($root . '/.git') ? $root . '/.git/publication.lock'
            : dirname($root) . '/.publication-' . hash('sha256', $root) . '.lock';
        return new ExecutionLock($path);
    }

    public static function withLock(string $repositoryRoot, callable $callback): mixed
    {
        $key = realpath($repositoryRoot) ?: $repositoryRoot;
        if (isset(self::$held[$key])) return $callback();
        return self::lock($repositoryRoot)->withExclusiveLock(function () use ($key, $callback): mixed {
            self::$held[$key] = true;
            try { return $callback(); }
            finally { unset(self::$held[$key]); }
        });
    }

}
