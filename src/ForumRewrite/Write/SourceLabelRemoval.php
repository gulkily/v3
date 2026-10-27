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
    public static function run(string $root, string $database, string $id, bool $repair, array $labels, string $artifactRoot): array
    {
        return (new ExecutionLock(dirname($database) . '/forum-rewrite.lock'))->withExclusiveLock(
            static fn (): array => PublicationLock::withLock($root, static function () use ($root, $database, $id, $repair, $labels, $artifactRoot): array {
                $journal = self::journal($root, $id);
                if (is_file($journal)) {
                    $pending = json_decode((string) file_get_contents($journal), true, 32, JSON_THROW_ON_ERROR);
                    if (($pending['source_id'] ?? null) !== $id) throw new RuntimeException('Invalid pending removal; inspect local journal.');
                    self::accept($root, $pending);
                }
                $record = LabelRemoval::prepare($root, $id, $repair && LabelOwnership::hasSource($root, $id) ? 'resolve' : 'delete', $labels);
                if (!$repair) $record['operation'] = 'delete';
                self::accept($root, $record);
                $commit = trim(LabelRemoval::git($root, ['rev-parse', 'HEAD']));
                LabelRemoval::read($root, $id);
                try {
                    (new StaticArtifactInvalidator($artifactRoot))->invalidateAllGeneratedPresentation();
                    (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();
                    (new \ForumRewrite\ReadModel\ReadModelStaleMarker($database))->clear();
                } catch (\Throwable $error) {
                    (new \ForumRewrite\ReadModel\ReadModelStaleMarker($database))->mark(['reason' => 'source_removal_refresh_failed', 'commit_sha' => $commit]);
                    throw new RuntimeException('Removal accepted at ' . $commit . '; refresh incomplete. Retry the same command: ' . $error->getMessage(), 0, $error);
                }
                $inspection = LabelRemoval::inspect($root, $id);
                return ['status' => 'ok', 'source_id' => $id, 'commit' => $commit,
                    'label_cleanup_status' => $inspection['review_candidates'] === [] ? 'complete' : 'unresolved',
                    'removed_labels' => array_keys($record['labels']), 'unresolved_labels' => array_values(array_diff($inspection['unresolved'], array_keys($record['labels'])))];
            })
        );
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
                // Include absent tracked files too: interruption may have unlinked them already.
                try { LabelRemoval::git($root, ['ls-files', '--error-unmatch', '--', $path]); $paths[] = $path; }
                catch (RuntimeException) { if (is_file($root . '/' . $path)) $paths[] = $path; }
                try { LabelRemoval::git($root, ['ls-files', '--error-unmatch', '--', $path . '.asc']); $paths[] = $path . '.asc'; }
                catch (RuntimeException) { if (is_file($root . '/' . $path . '.asc')) $paths[] = $path . '.asc'; }
            }
        }
        $journal = self::journal($root, $id);
        $temporary = tempnam(dirname($journal), '.label-removal-');
        if ($temporary === false || file_put_contents($temporary, json_encode($record, JSON_THROW_ON_ERROR)) === false || !rename($temporary, $journal)) throw new RuntimeException('Cannot persist removal recovery journal.');
        LabelRemoval::store($root, $record);
        foreach ($paths as $path) if ($path !== $relative && is_file($root . '/' . $path) && !unlink($root . '/' . $path)) throw new RuntimeException('Cannot remove ' . $path);
        LabelRemoval::git($root, array_merge(['add', '-A', '--'], $paths));
        if (trim(LabelRemoval::git($root, array_merge(['diff', '--cached', '--name-only', '--'], $paths))) !== '') {
            LabelRemoval::git($root, array_merge(['commit', '-m', 'Accept source label cleanup ' . $id, '--'], $paths));
        }
        LabelRemoval::read($root, $id);
        if (!unlink($journal)) throw new RuntimeException('Removal accepted; cannot retire journal. Retry the same command.');
    }

}
