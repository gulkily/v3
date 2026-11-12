<?php

declare(strict_types=1);
namespace ForumRewrite\Write;

use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\Canonical\LabelOwnership;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Support\PublicationLock;
use RuntimeException;

final class SourceLabelRemoval
{
    /** @param \Closure(string):void|null $progress receives one status line per step */
    public static function run(string $root, string $database, string $id, bool $repair, array $labels, string $artifactRoot, ?\Closure $progress = null): array
    {
        $say = static function (string $message) use ($progress): void { if ($progress !== null) $progress($message); };
        $say('Taking read-model lock');
        return (new ExecutionLock(dirname($database) . '/forum-rewrite.lock'))->withExclusiveLock(
            static fn (): array => PublicationLock::withLock($root, static function () use ($root, $database, $id, $repair, $labels, $artifactRoot, $progress, $say): array {
                $journal = self::journal($root, $id);
                if (is_file($journal)) {
                    $say('Finishing an interrupted removal');
                    $pending = json_decode((string) file_get_contents($journal), true, 32, JSON_THROW_ON_ERROR);
                    if (($pending['format'] ?? null) !== 'removal-recovery-v1' || !is_array($pending['record'] ?? null) || ($pending['record']['source_id'] ?? null) !== $id) throw new RuntimeException('Unsupported pending removal journal; no changes made. Only new-format recovery is supported.');
                    self::accept($root, $pending['record']);
                }
                // Decided before the commit: the targeted update needs a read model current at this HEAD.
                $context = $repair ? null : RemovalUpdateContext::forPost($root, $database, $id);
                $source = LabelRemoval::source($root, $id);
                $record = LabelRemoval::prepare($root, $id, $repair && LabelOwnership::hasSource($root, $id) ? 'resolve' : 'delete', $labels, $progress, $repair ? null : $database);
                if (!$repair) $record['operation'] = 'delete';
                $say('Committing removal (' . count($record['labels']) . ' label file(s))');
                self::accept($root, $record);
                $commit = trim(LabelRemoval::git($root, ['rev-parse', 'HEAD']));
                LabelRemoval::read($root, $id);
                try {
                    $updated = false;
                    if ($context !== null && $record['operation'] === 'delete') {
                        try {
                            $say('Updating read model for thread ' . $context['thread_id']);
                            (new \ForumRewrite\ReadModel\IncrementalReadModelUpdater($database, $root))
                                ->applyPostRemoval(LabelRemoval::read($root, $id), LabelRemoval::path($id), array_keys($record['labels']), $commit);
                            $say('Clearing pages for thread ' . $context['thread_id']);
                            (new StaticArtifactInvalidator($artifactRoot))->invalidateRemovedPost($context['thread_id'], $id, $context['tags'], $context['profile_slug']);
                            $updated = true;
                        } catch (\Throwable $error) {
                            $say('Targeted update failed (' . $error->getMessage() . '); falling back to a full rebuild');
                        }
                    }
                    if (!$updated) {
                        $say('Clearing generated pages');
                        (new StaticArtifactInvalidator($artifactRoot))->invalidateAllGeneratedPresentation();
                        $say('Rebuilding read model');
                        (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root), 'manual', $progress))->rebuild();
                    }
                    (new \ForumRewrite\ReadModel\ReadModelStaleMarker($database))->clear();
                } catch (\Throwable $error) {
                    (new \ForumRewrite\ReadModel\ReadModelStaleMarker($database))->mark(['reason' => 'source_removal_refresh_failed', 'commit_sha' => $commit]);
                    throw new RuntimeException('Removal accepted at ' . $commit . '; refresh incomplete. Retry the same command: ' . $error->getMessage(), 0, $error);
                }
                $images = $record['operation'] === 'delete' ? self::removeUnreferencedImages($root, [$source[1]], $say) : [];
                $say('Checking result');
                $inspection = LabelRemoval::inspect($root, $id, null, $database, $source);
                return ['status' => 'ok', 'source_id' => $id, 'commit' => $commit, 'removed_images' => $images,
                    'label_cleanup_status' => $inspection['review_candidates'] === [] ? 'complete' : 'unresolved',
                    'removed_labels' => array_keys($record['labels']), 'unresolved_labels' => array_values(array_diff($inspection['unresolved'], array_keys($record['labels'])))];
            }, static fn () => $say('Waiting for publication lock (another write is running)'))
        , static fn () => $say('Waiting for read-model lock (another command is running)'));
    }
    /**
     * Deletes several posts as one removal: one lock, one commit, one read-model transaction.
     * Each post keeps its own receipt, exactly as a separate run() would leave it. Used by
     * `delete-thread`. Rerunning after an interruption finishes any journaled acceptance first.
     *
     * @param list<string> $ids
     * @param \Closure(string):void|null $progress
     * @return array{status: string, commit: string, removed_images: list<string>, removed_labels: list<string>, unresolved_labels: list<string>}
     */
    public static function runMany(string $root, string $database, array $ids, string $artifactRoot, string $message, ?\Closure $progress = null): array
    {
        $say = static function (string $line) use ($progress): void { if ($progress !== null) $progress($line); };
        $say('Taking read-model lock');
        return (new ExecutionLock(dirname($database) . '/forum-rewrite.lock'))->withExclusiveLock(
            static fn (): array => PublicationLock::withLock($root, static function () use ($root, $database, $ids, $artifactRoot, $message, $progress, $say): array {
                foreach ($ids as $id) {
                    $journal = self::journal($root, $id);
                    if (!is_file($journal)) continue;
                    $say('Finishing an interrupted removal of ' . $id);
                    $pending = json_decode((string) file_get_contents($journal), true, 32, JSON_THROW_ON_ERROR);
                    if (($pending['format'] ?? null) !== 'removal-recovery-v1' || !is_array($pending['record'] ?? null) || ($pending['record']['source_id'] ?? null) !== $id) throw new RuntimeException('Unsupported pending removal journal; no changes made. Only new-format recovery is supported.');
                    self::accept($root, $pending['record']);
                }
                $say('Reading ' . count($ids) . ' post(s)');
                $contexts = [];
                $sources = [];
                $records = [];
                foreach ($ids as $id) {
                    $contexts[$id] = RemovalUpdateContext::forPost($root, $database, $id);
                    $sources[$id] = LabelRemoval::source($root, $id);
                    $record = LabelRemoval::prepare($root, $id, 'delete', [], null, $database);
                    $record['operation'] = 'delete';
                    $records[$id] = $record;
                }
                $labels = array_merge(...array_map(static fn (array $record): array => array_keys($record['labels']), array_values($records)));
                $say('Committing removal (' . count($records) . ' post(s), ' . count($labels) . ' label file(s))');
                self::acceptMany($root, array_values($records), $message);
                $commit = trim(LabelRemoval::git($root, ['rev-parse', 'HEAD']));
                try {
                    $updated = false;
                    if (!in_array(null, $contexts, true)) {
                        try {
                            $say('Updating read model');
                            $removals = [];
                            foreach ($ids as $id) $removals[] = [LabelRemoval::read($root, $id), LabelRemoval::path($id), array_keys($records[$id]['labels'])];
                            (new \ForumRewrite\ReadModel\IncrementalReadModelUpdater($database, $root))->applyPostRemovals($removals, $commit);
                            $say('Clearing pages');
                            $invalidator = new StaticArtifactInvalidator($artifactRoot);
                            foreach ($ids as $id) $invalidator->invalidateRemovedPost($contexts[$id]['thread_id'], $id, $contexts[$id]['tags'], $contexts[$id]['profile_slug']);
                            $updated = true;
                        } catch (\Throwable $error) {
                            $say('Targeted update failed (' . $error->getMessage() . '); falling back to a full rebuild');
                        }
                    }
                    if (!$updated) {
                        $say('Clearing generated pages');
                        (new StaticArtifactInvalidator($artifactRoot))->invalidateAllGeneratedPresentation();
                        $say('Rebuilding read model');
                        (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root), 'manual', $progress))->rebuild();
                    }
                    (new \ForumRewrite\ReadModel\ReadModelStaleMarker($database))->clear();
                } catch (\Throwable $error) {
                    (new \ForumRewrite\ReadModel\ReadModelStaleMarker($database))->mark(['reason' => 'source_removal_refresh_failed', 'commit_sha' => $commit]);
                    throw new RuntimeException('Removal accepted at ' . $commit . '; refresh incomplete. Retry the same command: ' . $error->getMessage(), 0, $error);
                }
                $images = self::removeUnreferencedImages($root, array_map(static fn (array $source): string => $source[1], array_values($sources)), $say);
                $say('Checking result');
                $unresolved = [];
                foreach ($ids as $id) {
                    $inspection = LabelRemoval::inspect($root, $id, null, $database, $sources[$id]);
                    $unresolved = array_merge($unresolved, array_diff($inspection['unresolved'], array_keys($records[$id]['labels'])));
                }
                return ['status' => 'ok', 'commit' => $commit, 'removed_images' => $images, 'removed_labels' => $labels, 'unresolved_labels' => array_values(array_unique($unresolved))];
            }, static fn () => $say('Waiting for publication lock (another write is running)'))
        , static fn () => $say('Waiting for read-model lock (another command is running)'));
    }

    /**
     * After the removal commit: deletes each image the removed posts attached, with its
     * thumbnails, unless a file still in the repository mentions the image's hash (another
     * post can attach the same image). Images are not in Git, so purge-history cannot reach
     * them; an operator who deletes a post expects its image gone too. A failure here never
     * undoes the accepted removal; it is reported and the image is left for a rerun.
     *
     * @param list<string> $sourceBytes removed posts' original bytes
     * @return list<string> image filenames removed
     */
    private static function removeUnreferencedImages(string $root, array $sourceBytes, \Closure $say): array
    {
        $references = [];
        foreach ($sourceBytes as $bytes) {
            $body = explode("\n\n", $bytes, 2)[1] ?? '';
            try { $reference = \ForumRewrite\Images\ImageReference::fromBody($body); }
            catch (\RuntimeException) { continue; }
            if ($reference !== null) $references[$reference['sha256']] = $reference;
        }
        if ($references === []) return [];
        $removed = [];
        try {
            $store = new \ForumRewrite\Images\ImageStore($root);
            foreach ($references as $hash => $reference) {
                exec('git -C ' . escapeshellarg($root) . ' grep -q -F -e ' . escapeshellarg($hash) . ' 2>/dev/null', $unused, $status);
                if ($status === 0) { $say('Keeping image ' . $hash . ' (still referenced)'); continue; }
                if ($status !== 1) throw new RuntimeException('Cannot check references to image ' . $hash);
                if ($store->remove($reference)) $removed[] = \ForumRewrite\Images\ImageReference::filename($reference);
            }
        } catch (\Throwable $error) {
            $say('Image cleanup incomplete: ' . $error->getMessage());
        }
        if ($removed !== []) $say('Removed ' . count($removed) . ' image(s)');

        return $removed;
    }

    private static function journal(string $root, string $id): string
    {
        LabelRemoval::path($id);
        static $gitDirectories = [];
        $gitDirectory = $gitDirectories[$root] ??= trim(LabelRemoval::git($root, ['rev-parse', '--absolute-git-dir']));
        return $gitDirectory . '/source-label-removal-' . $id . '.json';
    }

    private static function accept(string $root, array $record): void
    {
        self::acceptMany($root, [$record], 'Accept source label cleanup ' . $record['source_id']);
    }

    /**
     * Writes each record's receipt and journal, removes its files, then commits all of them
     * together. Each journal is retired only after that one commit, so an interrupted batch
     * resumes per post.
     *
     * @param list<array<string, mixed>> $records
     */
    private static function acceptMany(string $root, array $records, string $message): void
    {
        $receipts = [];
        $paths = [];
        $prepared = [];
        foreach ($records as $record) {
            $id = $record['source_id'];
            $record = LabelRemoval::decode(LabelRemoval::encode($record), $id);
            $signed = \ForumRewrite\Canonical\AcceptedRetraction::read($root, $id);
            if ($signed !== null) \ForumRewrite\Canonical\AcceptedRemoval::combine(
                $signed + ['source_id'=>$id,'operation'=>'delete','labels'=>[]], $record);
            $relative = LabelRemoval::path($id);
            $receipts[$relative] = true;
            $paths[] = $relative;
            $remove = [];
            if ($record['operation'] === 'delete') {
                foreach ($record['labels'] as $path => $hash) {
                    if (!preg_match('#^records/thread-labels/[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#D', $path)) throw new RuntimeException('Unsafe label path.');
                    if (is_file($root . '/' . $path) && !hash_equals($hash, hash_file('sha256', $root . '/' . $path))) throw new RuntimeException('Label changed; inspect before retry: ' . $path);
                    $remove[] = $path;
                }
                foreach (\ForumRewrite\Canonical\CanonicalPathResolver::postCandidates($id) as $path) {
                    if (is_file($root . '/' . $path) && !hash_equals($record['source_hash'], hash_file('sha256', $root . '/' . $path))) throw new RuntimeException('Source changed; inspect before retry.');
                    $remove[] = $path;
                }
                foreach ($remove as $path) {
                    foreach ([$path, $path . '.asc', $path . '.sig', $path . \ForumRewrite\Canonical\RecordReceipt::SUFFIX] as $candidate) $paths[] = $candidate;
                }
            }
            $prepared[] = $record;
        }
        $paths = array_values(array_unique($paths));
        // One lookup each in the index and HEAD instead of per-path Git processes. A rejected
        // commit may already have staged a deletion: the path is then absent from disk and
        // index but still belongs to the HEAD commit.
        $inIndex = array_flip(array_filter(explode("\0", LabelRemoval::git($root, array_merge(['ls-files', '-z', '--'], $paths)))));
        $inHead = self::headEntries($root, $paths);
        $paths = array_values(array_filter($paths, static fn (string $path): bool => isset($receipts[$path])
            || is_file($root . '/' . $path) || isset($inIndex[$path]) || isset($inHead[$path])));
        $journals = [];
        foreach ($prepared as $record) {
            $journal = self::journal($root, $record['source_id']);
            $temporary = tempnam(dirname($journal), '.label-removal-');
            if ($temporary === false || file_put_contents($temporary, json_encode(['format'=>'removal-recovery-v1','record'=>$record], JSON_THROW_ON_ERROR)) === false || !rename($temporary, $journal)) throw new RuntimeException('Cannot persist removal recovery journal.');
            $journals[] = $journal;
            LabelRemoval::store($root, $record);
        }
        foreach ($paths as $path) if (!isset($receipts[$path]) && is_file($root . '/' . $path) && !unlink($root . '/' . $path)) throw new RuntimeException('Cannot remove ' . $path);
        self::commitPaths($root, $paths, $inHead, $message);
        foreach ($prepared as $record) LabelRemoval::read($root, $record['source_id']);
        foreach ($journals as $journal) if (!unlink($journal)) throw new RuntimeException('Removal accepted; cannot retire journal. Retry the same command.');
    }

    /** @return array<string, string> path => blob ID, for the given paths present in HEAD */
    private static function headEntries(string $root, array $paths): array
    {
        $entries = [];
        foreach (array_filter(explode("\0", LabelRemoval::git($root, array_merge(['ls-tree', '-z', 'HEAD', '--'], $paths)))) as $line) {
            [$meta, $path] = explode("\t", $line, 2);
            $entries[$path] = (string) (explode(' ', $meta)[2] ?? '');
        }

        return $entries;
    }

    /**
     * Commits exactly these paths as they now are on disk (present: added, absent: deleted)
     * on top of HEAD, then stages the same paths. `git commit -- <paths>` would refresh the
     * whole index first, which costs seconds in a repository of several hundred thousand
     * files; fast-import reads only the trees it changes. Other staged changes are left
     * staged and uncommitted, as before. The pre-commit hook still runs.
     *
     * @param list<string> $paths
     * @param array<string, string> $inHead
     */
    private static function commitPaths(string $root, array $paths, array $inHead, string $message): void
    {
        $changes = '';
        foreach ($paths as $path) {
            $absolute = $root . '/' . $path;
            if (is_file($absolute)) {
                $bytes = (string) file_get_contents($absolute);
                if (isset($inHead[$path]) && hash_equals($inHead[$path], trim(LabelRemoval::gitWithInput($root, ['hash-object', '--stdin'], $bytes)))) continue;
                $changes .= 'M 100644 inline ' . $path . "\ndata " . strlen($bytes) . "\n" . $bytes . "\n";
            } elseif (isset($inHead[$path])) {
                $changes .= 'D ' . $path . "\n";
            }
        }
        if ($changes !== '') {
            $hook = $root . '/.git/hooks/pre-commit';
            if (is_file($hook) && is_executable($hook)) {
                LabelRemoval::git($root, array_merge(['update-index', '--add', '--remove', '--'], $paths));
                exec('cd ' . escapeshellarg($root) . ' && ' . escapeshellarg($hook) . ' 2>&1', $hookOutput, $hookStatus);
                if ($hookStatus !== 0) throw new RuntimeException('Git failed: pre-commit hook rejected the removal commit. ' . trim(implode("\n", $hookOutput)));
            }
            $head = trim(LabelRemoval::git($root, ['rev-parse', 'HEAD']));
            $branch = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' symbolic-ref -q HEAD'));
            $target = $branch !== '' ? $branch : 'refs/v3/pending-removal';
            [$name, $email, $time, $zone] = self::identity($root);
            $who = "{$name} <{$email}> {$time} {$zone}";
            LabelRemoval::gitWithInput($root, ['fast-import', '--quiet'],
                "commit {$target}\nauthor {$who}\ncommitter {$who}\ndata " . strlen($message) . "\n{$message}\nfrom {$head}\n{$changes}\n");
            if ($branch === '') {
                LabelRemoval::git($root, ['update-ref', '--no-deref', 'HEAD', $target]);
                LabelRemoval::git($root, ['update-ref', '-d', $target]);
            }
        }
        LabelRemoval::gitWithInput($root, ['update-index', '-z', '--add', '--remove', '--stdin'], implode("\0", $paths) . "\0");
    }

    /** @return array{string,string,string,string} committer name, email, epoch seconds, zone */
    private static function identity(string $root): array
    {
        $ident = trim(LabelRemoval::git($root, ['var', 'GIT_COMMITTER_IDENT']));
        if (preg_match('/^(.*) <([^>]*)> (\d+) ([+-]\d{4})$/', $ident, $match) !== 1) throw new RuntimeException('Cannot determine Git committer identity.');

        return [$match[1], $match[2], $match[3], $match[4]];
    }
}
