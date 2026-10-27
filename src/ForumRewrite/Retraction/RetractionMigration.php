<?php

declare(strict_types=1);
namespace ForumRewrite\Retraction;

use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\Support\ExecutionLock;

final class RetractionMigration
{
    public static function run(string $root, string $lockPath): array
    {
        return (new ExecutionLock($lockPath))->withExclusiveLock(static fn (): array => \ForumRewrite\Support\PublicationLock::withLock($root, static function () use ($root): array {
            $result = ['status' => 'complete', 'migrated' => [], 'unresolved' => []];
            // Keep existing source-removal records in place, accepting their historical proof once.
            foreach (glob($root . '/records/post-removals/*.json') ?: [] as $path) {
                try {
                    $record = LabelRemoval::read($root, basename($path, '.json'));
                    if (($record['acceptance_version'] ?? null) === 2) continue;
                    $record['acceptance_version'] = 2;
                    $original = file_get_contents($path);
                    $relative = LabelRemoval::path($record['source_id']);
                    try {
                        LabelRemoval::store($root, $record);
                        LabelRemoval::git($root, ['add', '--', $relative]);
                        LabelRemoval::git($root, ['-c', 'user.name=Forum Rewrite', '-c', 'user.email=forum-rewrite@example.invalid',
                            'commit', '-m', 'Migrate accepted source removal ' . $record['source_id'], '--', $relative]);
                    } catch (\Throwable $error) {
                        file_put_contents($path, $original);
                        LabelRemoval::git($root, ['reset', '-q', 'HEAD', '--', $relative]);
                        throw $error;
                    }
                    $result['migrated'][] = $record['source_id'];
                } catch (\Throwable $error) { $result['unresolved'][basename($path)] = $error->getMessage(); }
            }
            if ($result['unresolved'] !== []) $result['status'] = 'partial';
            return $result;
        }));
    }
}
