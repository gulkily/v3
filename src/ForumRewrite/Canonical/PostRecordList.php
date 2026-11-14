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
            return ['record_id' => $target, 'kind' => self::label(RemovalTarget::family($target), []), 'family' => RemovalTarget::family($target),
                'path' => $path, 'author_identity_id' => ($headers['Author-Identity-ID'] ?? '') ?: null,
                'created_at' => $headers['Target-Created-At'] ?? $headers['Post-Created-At'] ?? '', 'summary' => 'Retracted ' . ($headers['Accepted-At'] ?? ''), 'retracted' => true];
        }
        $recordId = $family === 'post' ? ($headers['Post-ID'] ?? $id) : ($headers['Record-ID'] ?? $id);
        $tags = preg_split('/\s+/', trim($headers['Tags'] ?? $headers['Labels'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $summary = match ($family) {
            'post-formats' => 'Format: ' . ($headers['Body-Format'] ?? $headers['Format'] ?? ''),
            'thread-subjects' => 'Title: ' . ($headers['Subject'] ?? ''),
            'post' => 'The post itself',
            default => ($headers['Operation'] ?? 'add') . ' ' . implode(' ', $tags),
        };
        return ['record_id' => $recordId, 'kind' => self::label($family, $tags), 'family' => $family, 'path' => $path,
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
