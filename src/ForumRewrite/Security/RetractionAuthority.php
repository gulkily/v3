<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

use ForumRewrite\Canonical\ApprovalSeedRecordParser;
use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\PostRetractionRecord;

/** Git history belongs to the local operator; archive claims never supply trust. */
final class RetractionAuthority
{
    private static array $cache = [];

    public static function acceptedOperator(string $root, string $path, string $contents, PostRetractionRecord $record): bool
    {
        $head = trim(self::git($root, ['rev-parse', 'HEAD']));
        $key = hash('sha256', serialize([$root, $head, $path, $contents]));
        if (isset(self::$cache[$key])) return self::$cache[$key];
        if (count(self::$cache) >= 1024) self::$cache = [];
        $actor = $record->signerIdentityId();
        $subject = 'Retract post ' . $record->postId . ' by ' . $actor;
        $seedPath = CanonicalPathResolver::approvalSeed(substr($actor, 8));
        $commits = preg_split('/\s+/', trim(self::git($root, ['log', '--diff-filter=A', '--format=%H', '--', $path]))) ?: [];
        foreach ($commits as $commit) {
            if (!preg_match('/^[a-f0-9]{40}$/D', $commit)) continue;
            if (trim(self::git($root, ['show', '-s', '--format=%s', $commit])) !== $subject
                || self::git($root, ['show', $commit . ':' . $path]) !== $contents) continue;
            $parents = explode(' ', trim(self::git($root, ['show', '-s', '--format=%P', $commit])));
            if (count($parents) !== 1 || !preg_match('/^[a-f0-9]{40}$/D', $parents[0])) continue;
            $parent = $parents[0];
            // A symlink must not become an authority seed during historical replay.
            if (!str_starts_with(self::git($root, ['ls-tree', $parent, '--', $seedPath]), '100644 blob ')) continue;
            try {
                $seed = (new ApprovalSeedRecordParser())->parse(self::git($root, ['show', $parent . ':' . $seedPath]));
                if ($seed->approvedIdentityId === $actor) return self::$cache[$key] = true;
            } catch (\Throwable) {
                continue;
            }
        }
        return self::$cache[$key] = false;
    }

    private static function git(string $root, array $arguments): string
    {
        $command = 'git -C ' . escapeshellarg($root) . ' ' . implode(' ', array_map('escapeshellarg', $arguments));
        return (string) shell_exec($command . ' 2>/dev/null');
    }
}
