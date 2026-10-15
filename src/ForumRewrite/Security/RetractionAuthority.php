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
        return self::acceptedCommitMessage($root, $path, $contents, $record) !== null;
    }

    /** Display attribution is evidence from the same local event that accepted operator authority. */
    public static function acceptedAttribution(string $root, string $path, string $contents, PostRetractionRecord $record): string
    {
        if ($record->classification !== 'operator') return 'author';
        $message = self::acceptedCommitMessage($root, $path, $contents, $record);
        if ($message === null || preg_match_all('/^Retraction-Attribution: (.*)$/m', $message, $matches) !== 1) return 'operator';
        $evidence = json_decode($matches[1][0], true);
        if (!is_array($evidence) || ($evidence['version'] ?? null) !== 1
            || ($evidence['sha256'] ?? null) !== hash('sha256', $contents)
            || ($evidence['actor'] ?? null) !== $record->signerIdentityId()
            || ($evidence['author'] ?? null) !== $record->authorIdentityId
            || ($evidence['attribution'] ?? null) !== 'author'
            || !is_string($evidence['username'] ?? null)
            || trim($evidence['username']) === ''
            || strtolower($evidence['username']) !== $evidence['username']) return 'operator';
        return 'author';
    }

    private static function acceptedCommitMessage(string $root, string $path, string $contents, PostRetractionRecord $record): ?string
    {
        $head = trim(self::git($root, ['rev-parse', 'HEAD']));
        $key = hash('sha256', serialize([$root, $head, $path, $contents]));
        if (array_key_exists($key, self::$cache)) return self::$cache[$key];
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
            if (preg_match('/^100(?:644|755) blob /', self::git($root, ['ls-tree', $parent, '--', $seedPath])) !== 1) continue;
            try {
                $seed = (new ApprovalSeedRecordParser())->parse(self::git($root, ['show', $parent . ':' . $seedPath]));
                if ($seed->approvedIdentityId === $actor) return self::$cache[$key] = self::git($root, ['show', '-s', '--format=%B', $commit]);
            } catch (\Throwable) {
                continue;
            }
        }
        return self::$cache[$key] = null;
    }

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
