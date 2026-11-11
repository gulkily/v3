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
                $say('Checking result');
                $inspection = LabelRemoval::inspect($root, $id, null, $database);
                return ['status' => 'ok', 'source_id' => $id, 'commit' => $commit,
                    'label_cleanup_status' => $inspection['review_candidates'] === [] ? 'complete' : 'unresolved',
                    'removed_labels' => array_keys($record['labels']), 'unresolved_labels' => array_values(array_diff($inspection['unresolved'], array_keys($record['labels'])))];
            }, static fn () => $say('Waiting for publication lock (another write is running)'))
        , static fn () => $say('Waiting for read-model lock (another command is running)'));
    }
    private static function journal(string $root, string $id): string
    {
        LabelRemoval::path($id);
        $gitDirectory = trim(LabelRemoval::git($root, ['rev-parse', '--absolute-git-dir']));
        return $gitDirectory . '/source-label-removal-' . $id . '.json';
    }

    private static function accept(string $root, array $record): void
    {
        $id = $record['source_id'];
        $record = LabelRemoval::decode(LabelRemoval::encode($record), $id);
        $signed = \ForumRewrite\Canonical\AcceptedRetraction::read($root, $id);
        if ($signed !== null) \ForumRewrite\Canonical\AcceptedRemoval::combine(
            $signed + ['source_id'=>$id,'operation'=>'delete','labels'=>[]], $record);
        $relative = LabelRemoval::path($id);
        $paths = [$relative];
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
        // One lookup each in the index and HEAD instead of per-path Git processes. A rejected
        // commit may already have staged a deletion: the path is then absent from disk and
        // index but still belongs to the HEAD commit.
        $inIndex = array_flip(array_filter(explode("\0", LabelRemoval::git($root, array_merge(['ls-files', '-z', '--'], $paths)))));
        $inHead = self::headEntries($root, $paths);
        $paths = array_values(array_filter($paths, static fn (string $path): bool => $path === $relative
            || is_file($root . '/' . $path) || isset($inIndex[$path]) || isset($inHead[$path])));
        $journal = self::journal($root, $id);
        $temporary = tempnam(dirname($journal), '.label-removal-');
        if ($temporary === false || file_put_contents($temporary, json_encode(['format'=>'removal-recovery-v1','record'=>$record], JSON_THROW_ON_ERROR)) === false || !rename($temporary, $journal)) throw new RuntimeException('Cannot persist removal recovery journal.');
        LabelRemoval::store($root, $record);
        foreach ($paths as $path) if ($path !== $relative && is_file($root . '/' . $path) && !unlink($root . '/' . $path)) throw new RuntimeException('Cannot remove ' . $path);
        self::commitPaths($root, $paths, $inHead, 'Accept source label cleanup ' . $id);
        LabelRemoval::read($root, $id);
        if (!unlink($journal)) throw new RuntimeException('Removal accepted; cannot retire journal. Retry the same command.');
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
