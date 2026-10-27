<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

use ForumRewrite\Canonical\CanonicalPathResolver;

/** New operator actions require a currently committed local operator seed. */
final class RetractionAuthority
{
    public static function currentOperator(string $root, string $actor): bool
    {
        if (!ApprovalPolicy::isOperator($root, $actor)) return false;
        $path = CanonicalPathResolver::approvalSeed(substr($actor, 8));
        if (preg_match('/^100(?:644|755) blob /', self::git($root, ['ls-tree', 'HEAD', '--', $path])) !== 1) return false;
        return self::git($root, ['show', 'HEAD:' . $path]) === (string) file_get_contents($root . '/' . $path);
    }

    private static function git(string $root, array $arguments): string
    {
        $command = 'git -C ' . escapeshellarg($root) . ' ' . implode(' ', array_map('escapeshellarg', $arguments));
        return (string) shell_exec($command . ' 2>/dev/null');
    }
}
