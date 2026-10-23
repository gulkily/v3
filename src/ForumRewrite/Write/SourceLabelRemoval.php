<?php

declare(strict_types=1);
namespace ForumRewrite\Write;

use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\Canonical\LabelOwnership;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Retraction\PublicationGuard;
use RuntimeException;

final class SourceLabelRemoval
{
    public static function run(string $root, string $database, string $id, bool $repair, array $labels, string $artifactRoot): array
    {
        return (new ExecutionLock(dirname($database) . '/forum-rewrite.lock'))->withExclusiveLock(
            static fn (): array => PublicationGuard::withLock($root, static function () use ($root, $database, $id, $repair, $labels, $artifactRoot): array {
                $record = LabelRemoval::prepare($root, $id, $repair && LabelOwnership::hasSource($root, $id) ? 'resolve' : 'delete', $labels);
                if (!$repair) $record['operation'] = 'delete';
                $relative = LabelRemoval::path($id);
                $paths = [$relative];
                $remove = [];
                if ($record['operation'] === 'delete') {
                    foreach ($record['labels'] as $path => $hash) {
                        if (is_file($root . '/' . $path) && !hash_equals($hash, hash_file('sha256', $root . '/' . $path))) {
                            throw new RuntimeException('Label changed since removal acceptance; inspect before retry: ' . $path);
                        }
                        $remove[] = $path;
                    }
                    foreach (\ForumRewrite\Canonical\CanonicalPathResolver::postCandidates($id) as $path) {
                        if (is_file($root . '/' . $path) && !hash_equals($record['source_hash'], hash_file('sha256', $root . '/' . $path))) {
                            throw new RuntimeException('Source changed since removal acceptance; inspect before retry.');
                        }
                        $remove[] = $path;
                    }
                    foreach ($remove as $path) {
                        if (is_file($root . '/' . $path)) $paths[] = $path;
                        if (is_file($root . '/' . $path . '.asc')) $paths[] = $path . '.asc';
                    }
                }
                // No source payload is stored in removal evidence.
                LabelRemoval::store($root, $record);
                foreach ($paths as $path) if ($path !== $relative && is_file($root . '/' . $path)) {
                    if (!unlink($root . '/' . $path)) throw new RuntimeException('Cannot remove ' . $path);
                }
                LabelRemoval::git($root, array_merge(['add', '-A', '--'], $paths));
                if (trim(LabelRemoval::git($root, array_merge(['diff', '--cached', '--name-only', '--'], $paths))) !== '') {
                    LabelRemoval::git($root, array_merge(['commit', '-m', 'Accept source label cleanup ' . $id, '--'], $paths));
                }
                $commit = trim(LabelRemoval::git($root, ['rev-parse', 'HEAD']));
                LabelRemoval::read($root, $id);
                try {
                    (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();
                    (new StaticArtifactInvalidator($artifactRoot))->invalidateAllGeneratedPresentation();
                } catch (\Throwable $error) {
                    throw new RuntimeException('Removal accepted at ' . $commit . '; refresh incomplete. Retry the same command: ' . $error->getMessage(), 0, $error);
                }
                $inspection = LabelRemoval::inspect($root, $id);
                return ['status' => 'ok', 'source_id' => $id, 'commit' => $commit,
                    'removed_labels' => array_keys($record['labels']), 'unresolved_labels' => array_values(array_diff($inspection['unresolved'], array_keys($record['labels'])))];
            })
        );
    }
}
