<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/**
 * The signed records that target one post: the post itself, likes and flags on it, labels and
 * titles on its thread when it is a root (or titles it set as a reply), format changes, and the
 * receipts of reactions on it that were retracted. Found with `git grep` over the record
 * directories, so it reads current files and needs no read-model index.
 */
final class PostRecordList
{
    /** Directory => header lines that point at the post; `{id}` is the post ID. */
    private const POINTERS = [
        'records/post-reactions' => ['Target-ID: {id}', 'Post-ID: {id}'],
        'records/post-formats' => ['Post-ID: {id}'],
        'records/thread-subjects' => ['Source-Post-ID: {id}'],
        'records/post-retraction-acceptances' => ['Parent-ID: {id}'],
        'records/post-removals' => ['Parent-ID: {id}'],
    ];

    /** Pointers that apply only when the post is a thread's root. */
    private const ROOT_POINTERS = [
        'records/thread-labels' => ['Target-ID: {id}', 'Thread-ID: {id}'],
        'records/thread-subjects' => ['Thread-ID: {id}'],
    ];

    /**
     * @return list<array{record_id: string, kind: string, family: string, path: string,
     *     author_identity_id: ?string, created_at: string, summary: string, retracted: bool}>
     */
    public static function forPost(string $root, string $postId, bool $isRoot): array
    {
        $rows = [];
        foreach (CanonicalPathResolver::postCandidates($postId) as $path) {
            if (is_file($root . '/' . $path)) { $rows[$path] = self::row($root, $path, 'post'); break; }
        }
        $pointers = self::POINTERS;
        if ($isRoot) foreach (self::ROOT_POINTERS as $dir => $lines) $pointers[$dir] = array_merge($pointers[$dir] ?? [], $lines);
        foreach ($pointers as $dir => $lines) {
            foreach (self::grep($root, $dir, array_map(static fn (string $line): string => str_replace('{id}', $postId, $line), $lines)) as $path) {
                $row = self::row($root, $path, basename($dir));
                if ($row !== null) $rows[$path] = $row;
            }
        }
        $rows = array_values(array_filter($rows));
        usort($rows, static fn (array $a, array $b): int => [$a['kind'] !== 'post', $a['created_at'], $a['record_id']] <=> [$b['kind'] !== 'post', $b['created_at'], $b['record_id']]);
        return $rows;
    }

    /**
     * The record behind each current like or flag in a thread, for linking a reactor's name to
     * `/records/<Record-ID>`: likes and flags on the given posts, and likes on the thread itself
     * (keyed by the root's ID). One `git grep` for the whole thread. Only `add`
     * records count; when an actor added the same tag more than once, the newest record wins.
     * Retracted records are deleted, so they are never returned.
     *
     * @param list<string> $postIds
     * @return array<string, string> self::reactionKey(target, tag, identity) => Record-ID
     */
    public static function reactionRecordIds(string $root, array $postIds, string $rootPostId): array
    {
        $postIds = array_values(array_unique(array_filter(array_map('strval', $postIds), static fn (string $id): bool => $id !== '')));
        if ($postIds === []) return [];
        $sources = [
            'records/post-reactions' => array_merge(...array_map(static fn (string $id): array => ["Target-ID: {$id}", "Post-ID: {$id}"], $postIds)),
            'records/thread-labels' => ["Target-ID: {$rootPostId}", "Thread-ID: {$rootPostId}"],
        ];
        $found = [];
        foreach (self::grepHeaderLines($root, $sources, ['Target-ID', 'Post-ID', 'Thread-ID']) as $dir => $paths) {
            foreach ($paths as $path) {
                try { $headers = (new GenericTextRecordParser())->parse((string) RecordLimits::read($root . '/' . $path))->headers; }
                catch (\RuntimeException) { continue; }
                if (($headers['Operation'] ?? 'add') !== 'add') continue;
                $target = $dir === 'records/thread-labels' ? $rootPostId : (string) ($headers['Target-ID'] ?? $headers['Post-ID'] ?? '');
                $identity = (string) ($headers['Author-Identity-ID'] ?? $headers['Actor-Identity-ID'] ?? '');
                if ($target === '' || $identity === '' || ($dir === 'records/post-reactions' && !in_array($target, $postIds, true))) continue;
                $recordId = (string) ($headers['Record-ID'] ?? basename($path, '.txt'));
                $createdAt = (string) ($headers['Created-At'] ?? '');
                $tags = preg_split('/\s+/', trim($headers['Tags'] ?? $headers['Labels'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach (array_intersect($tags, ['like', 'flag']) as $tag) {
                    $key = self::reactionKey($target, $tag, $identity);
                    if (!isset($found[$key]) || [$createdAt, $recordId] > $found[$key]) $found[$key] = [$createdAt, $recordId];
                }
            }
        }
        return array_map(static fn (array $pair): string => $pair[1], $found);
    }

    public static function reactionKey(string $targetId, string $tag, string $identityId): string
    {
        return $targetId . "\t" . $tag . "\t" . $identityId;
    }

    /**
     * Like grep(), for many directories and lines at once, in one Git call. It searches for the
     * header names alone (a fixed-string search stays fast however long the thread is; an anchored
     * regex per post ID does not) and keeps files where a whole line equals one of $lines.
     *
     * @param array<string, list<string>> $linesByDir @return array<string, list<string>> dir => paths
     */
    private static function grepHeaderLines(string $root, array $linesByDir, array $headerNames): array
    {
        $dirs = array_values(array_filter(array_keys($linesByDir), static fn (string $dir): bool => is_dir($root . '/' . $dir)));
        if ($dirs === []) return [];
        $args = ['grep', '-F', '--full-name'];
        foreach ($headerNames as $name) array_push($args, '-e', $name . ': ');
        $args[] = '--';
        foreach ($dirs as $dir) $args[] = $dir . '/*.txt';
        try { $out = LabelRemoval::git($root, $args); }
        catch (\RuntimeException) { return []; } // git grep exits non-zero when nothing matches
        $wanted = [];
        foreach ($linesByDir as $dir => $lines) foreach ($lines as $line) $wanted[$dir][$line] = true;
        $paths = [];
        foreach (explode("\n", $out) as $hit) {
            $end = strpos($hit, '.txt:');
            if ($end === false) continue;
            $path = substr($hit, 0, $end + 4);
            $dir = dirname($path);
            if (isset($wanted[$dir][rtrim(substr($hit, $end + 5), "\r")])) $paths[$dir][$path] = true;
        }
        return array_map('array_keys', $paths);
    }

    /** @return list<string> repository-relative paths of tracked .txt records in $dir with any of $lines */
    private static function grep(string $root, string $dir, array $lines): array
    {
        if (!is_dir($root . '/' . $dir)) return [];
        // git grep has no whole-line flag: anchor each literal line as an extended regex.
        $args = ['grep', '-l', '-E'];
        foreach ($lines as $line) array_push($args, '-e', '^' . preg_replace('/[.^$*+?(){}|\[\]\\\\]/', '\\\\$0', $line) . '$');
        try { $out = LabelRemoval::git($root, array_merge($args, ['--', $dir . '/*.txt'])); }
        catch (\RuntimeException) { return []; } // git grep exits non-zero when nothing matches
        return array_values(array_filter(explode("\n", trim($out)), static fn (string $path): bool => $path !== '' && str_ends_with($path, '.txt')));
    }

    /** @return array{record_id: string, kind: string, family: string, path: string, author_identity_id: ?string, created_at: string, summary: string, retracted: bool}|null */
    private static function row(string $root, string $path, string $family): ?array
    {
        try { $headers = (new GenericTextRecordParser())->parse((string) RecordLimits::read($root . '/' . $path))->headers; }
        catch (\RuntimeException) { return null; }
        $id = basename($path, '.txt');
        $receipt = in_array($family, ['post-retraction-acceptances', 'post-removals'], true);
        if ($receipt) {
            $target = $headers['Target-ID'] ?? $headers['Post-ID'] ?? '';
            if (RemovalTarget::family($target) === 'post') return null; // a post's own removal is not a record under its parent
            // Only likes can be retracted or deleted as reactions, so a reaction receipt is a like's.
            return ['record_id' => $target, 'kind' => 'Like', 'family' => RemovalTarget::family($target),
                'path' => $path, 'author_identity_id' => ($headers['Author-Identity-ID'] ?? '') ?: null,
                'created_at' => $headers['Target-Created-At'] ?? $headers['Post-Created-At'] ?? '', 'summary' => 'Retracted ' . ($headers['Accepted-At'] ?? ''), 'retracted' => true];
        }
        $recordId = $family === 'post' ? ($headers['Post-ID'] ?? $id) : ($headers['Record-ID'] ?? $id);
        $tags = preg_split('/\s+/', trim($headers['Tags'] ?? $headers['Labels'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        // "Like" (and its Retract button) only for what the server would retract: an add, not owned by a post's hashtag.
        $plainAdd = ($headers['Operation'] ?? 'add') === 'add' && !isset($headers['Source-Post-ID']);
        $summary = match ($family) {
            'post-formats' => 'Format: ' . ($headers['Body-Format'] ?? $headers['Format'] ?? ''),
            'thread-subjects' => 'Title: ' . ($headers['Subject'] ?? ''),
            'post' => 'The post itself',
            default => ($headers['Operation'] ?? 'add') . ' ' . implode(' ', $tags),
        };
        return ['record_id' => $recordId, 'kind' => self::label($family, $plainAdd ? $tags : []), 'family' => $family, 'path' => $path,
            'author_identity_id' => ($headers['Author-Identity-ID'] ?? $headers['Actor-Identity-ID'] ?? '') ?: null, 'created_at' => $headers['Created-At'] ?? '',
            'summary' => trim($summary), 'retracted' => false];
    }

    private static function label(string $family, array $tags): string
    {
        return match ($family) {
            'post' => 'Post',
            'post-reactions', 'post-reaction' => $tags === ['like'] ? 'Like' : ($tags === ['flag'] ? 'Flag' : 'Reaction'),
            'thread-labels', 'thread-label' => $tags === ['like'] ? 'Like' : 'Label',
            'post-formats' => 'Format',
            'thread-subjects' => 'Title',
            default => 'Record',
        };
    }
}
