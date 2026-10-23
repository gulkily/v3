<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

use RuntimeException;

/** Operator-accepted, content-bound evidence; ordinary import commits grant no authority. */
final class LabelRemoval
{
    public static function path(string $sourceId): string
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,200}$/D', $sourceId)) throw new RuntimeException('Invalid source post ID.');
        return 'records/post-removals/' . $sourceId . '.json';
    }

    public static function git(string $root, array $args): string
    {
        $pipes = [];
        $process = proc_open(array_merge(['git', '-C', $root], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot run Git.');
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('Git failed: ' . trim($err));
        return $out;
    }

    public static function read(string $root, string $sourceId): ?array
    {
        $path = self::path($sourceId);
        if (!is_file($root . '/' . $path)) return null;
        if (is_link($root . '/' . $path) || filesize($root . '/' . $path) > 1048576) throw new RuntimeException('Unsafe removal evidence: ' . $path);
        $bytes = (string) file_get_contents($root . '/' . $path);
        $record = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($record) || ($record['source_id'] ?? null) !== $sourceId
            || !in_array($record['operation'] ?? '', ['delete', 'resolve'], true)
            || !preg_match('/^[a-f0-9]{64}$/D', $record['source_hash'] ?? '')
            || !is_array($record['labels'] ?? null) || !is_array($record['post'] ?? null)) {
            throw new RuntimeException('Invalid removal evidence: ' . $path);
        }
        if (($record['post']['post_id'] ?? null) !== $sourceId
            || !is_string($record['post']['thread_id'] ?? null)
            || !is_string($record['post']['created_at'] ?? null)) throw new RuntimeException('Invalid removal structure: ' . $path);
        self::path($record['post']['thread_id']);
        foreach ($record['labels'] as $labelPath => $hash) {
            if (!is_string($labelPath) || !preg_match('#^records/thread-labels/[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#D', $labelPath)
                || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) throw new RuntimeException('Invalid removal label binding.');
        }
        // The exact bytes must have been accepted locally, not just copied in an archive.
        $commits = preg_split('/\s+/', trim(self::git($root, ['log', '--format=%H', '--', $path])));
        foreach ($commits as $commit) {
            if ($commit === '') continue;
            if (trim(self::git($root, ['show', '-s', '--format=%s', $commit])) !== 'Accept source label cleanup ' . $sourceId) continue;
            if (self::git($root, ['show', $commit . ':' . $path]) === $bytes) return $record;
        }
        throw new RuntimeException('Removal evidence lacks trusted local acceptance; restore trusted Git history: ' . $path);
    }

    public static function all(string $root): array
    {
        $records = [];
        if (file_exists($root . '/.git')) {
            $tracked = preg_split('/\n/', trim(self::git($root, ['ls-tree', '-r', '--name-only', 'HEAD', '--', 'records/post-removals'])));
            foreach ($tracked as $path) if ($path !== '' && !is_file($root . '/' . $path)) throw new RuntimeException('Tracked removal evidence missing; restore trusted repository: ' . $path);
        }
        foreach (glob($root . '/records/post-removals/*.json') ?: [] as $path) {
            $id = basename($path, '.json');
            $records[$id] = self::read($root, $id);
        }
        return $records;
    }

    public static function source(string $root, string $id): array
    {
        self::path($id);
        foreach (CanonicalPathResolver::postCandidates($id) as $path) {
            if (is_file($root . '/' . $path)) return [$path, (string) file_get_contents($root . '/' . $path)];
        }
        // Recovery from deletions made before this feature; never infer ownership from time.
        foreach (CanonicalPathResolver::postCandidates($id) as $path) {
            $commits = preg_split('/\s+/', trim(self::git($root, ['log', '--format=%H', '--', $path])));
            foreach ($commits as $commit) {
                if ($commit === '') continue;
                try { return [$path, self::git($root, ['show', $commit . ':' . $path])]; }
                catch (RuntimeException) { /* deletion commit; inspect earlier version */ }
            }
        }
        throw new RuntimeException('Source evidence unavailable; restore trusted history before repair.');
    }

    public static function inspect(string $root, string $id): array
    {
        [$sourcePath, $bytes] = self::source($root, $id);
        try { $post = (new PostRecordParser())->parse($bytes); }
        catch (CanonicalRecordParseException $error) {
            if ($error->getMessage() !== 'Missing required post header: Created-At') throw $error;
            $date = LegacyPostTimestamp::resolve($root, $sourcePath, $bytes);
            if ($date === null) throw new RuntimeException('Legacy source timestamp evidence unavailable.');
            $post = (new PostRecordParser())->parse(preg_replace('/\n/', "\nCreated-At: {$date}\n", $bytes, 1));
        }
        if ($post->postId !== $id || RetractedPosts::isAuthorityPost($post)) throw new RuntimeException('Protected or mismatched source post.');
        $proven = []; $unresolved = []; $candidates = [];
        $repository = new CanonicalRecordRepository($root);
        foreach (glob($root . '/records/thread-labels/*.txt') ?: [] as $path) {
            $relative = 'records/thread-labels/' . basename($path);
            $label = $repository->loadThreadLabel($relative);
            if ($label->threadId !== ($post->threadId ?? $post->postId)) continue;
            if ($label->sourcePostId === $id && hash_equals($label->sourcePostSha256 ?? '', hash('sha256', $bytes))
                && $label->authorIdentityId === $post->authorIdentityId) {
                $proven[$relative] = hash_file('sha256', $path);
            } elseif ($label->sourcePostId === $id || ($label->sourcePostId === null
                && $label->authorIdentityId === $post->authorIdentityId
                && preg_match('/(?:^|[^A-Za-z0-9-])#(?:' . implode('|', array_map(static fn (string $tag): string => preg_quote($tag, '/'), $label->labels)) . ')(?:$|[^A-Za-z0-9-])/m', $post->body))) {
                $candidates[] = $relative;
            }
            if ($label->sourcePostId === null || ($label->sourcePostId === $id && !isset($proven[$relative]))) $unresolved[] = $relative;
        }
        $accepted = self::read($root, $id);
        $unresolved = array_values(array_diff($unresolved, array_keys($accepted['labels'] ?? [])));
        return ['source_path' => $sourcePath, 'source_hash' => hash('sha256', $bytes), 'post' => $post, 'proven' => $proven, 'unresolved' => $unresolved, 'review_candidates' => array_values(array_diff($candidates, array_keys($accepted['labels'] ?? [])))];
    }
    /** Prepare bytes before mutation; caller commits only these paths under its write lock. */
    public static function prepare(string $root, string $id, string $operation, array $explicitLabelIds = []): array
    {
        if (!in_array($operation, ['delete', 'resolve'], true)) throw new RuntimeException('Invalid cleanup operation.');
        $existing = self::read($root, $id);
        if ($existing !== null && $existing['operation'] === 'delete' && $explicitLabelIds === []) return $existing;
        $inspection = self::inspect($root, $id);
        $post = $inspection['post'];
        $labels = array_merge($existing['labels'] ?? [], $inspection['proven']);
        foreach ($explicitLabelIds as $labelId) {
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $labelId)) throw new RuntimeException('Invalid label ID.');
            $path = CanonicalPathResolver::threadLabel($labelId);
            $label = (new CanonicalRecordRepository($root))->loadThreadLabel($path);
            if ($label->threadId !== ($post->threadId ?? $id) || ($label->sourcePostId !== null && $label->sourcePostId !== $id)) {
                throw new RuntimeException('Explicit resolution conflicts with label target or source.');
            }
            $labels[$path] = hash_file('sha256', $root . '/' . $path);
        }
        ksort($labels);
        $effectiveOperation = ($existing['operation'] ?? null) === 'delete' ? 'delete' : $operation;
        return ['source_id' => $id, 'source_hash' => $inspection['source_hash'],
            'operation' => $effectiveOperation, 'accepted_at' => ($existing['operation'] ?? null) === $effectiveOperation ? $existing['accepted_at'] : gmdate('Y-m-d\TH:i:s\Z'),
            'actor' => 'local-operator', 'labels' => $labels,
            'post' => ['post_id' => $id, 'thread_id' => $post->threadId ?? $id, 'parent_id' => $post->parentId,
                'created_at' => $post->createdAt, 'author_identity_id' => $post->authorIdentityId, 'thread_type' => $post->threadType]];
    }

    public static function store(string $root, array $record): string
    {
        $relative = self::path($record['source_id']);
        $directory = dirname($root . '/' . $relative);
        if (!is_dir($directory) && !mkdir($directory, 0775, true)) throw new RuntimeException('Cannot create removal evidence directory.');
        $temporary = tempnam($directory, '.pending-');
        try {
            if (file_put_contents($temporary, json_encode($record, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n") === false
                || !rename($temporary, $root . '/' . $relative)) throw new RuntimeException('Cannot persist removal evidence.');
        } finally { if (is_file($temporary)) unlink($temporary); }
        return $relative;
    }

    public static function generation(string $root): string
    {
        $records = self::all($root);
        ksort($records);
        return hash('sha256', json_encode($records, JSON_THROW_ON_ERROR));
    }

}
