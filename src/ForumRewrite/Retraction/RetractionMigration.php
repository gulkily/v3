<?php

declare(strict_types=1);
namespace ForumRewrite\Retraction;

use ForumRewrite\Canonical\{AcceptedRetraction, CanonicalRecordRepository, LabelRemoval, PostRecordParser};
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Write\RetractionDeletion;

final class RetractionMigration
{
    public static function run(string $root, string $lockPath): array
    {
        return (new ExecutionLock($lockPath))->withExclusiveLock(static fn (): array => \ForumRewrite\Support\PublicationLock::withLock($root, static function () use ($root): array {
            $result = ['status' => 'complete', 'migrated' => [], 'unresolved' => []];
            $repository = new CanonicalRecordRepository($root);
            // Retry an interrupted conversion even if its legacy text file was already removed.
            foreach (glob($root . '/records/post-retractions/post-retraction-*.json') ?: [] as $path) {
                try {
                    $id = substr(basename($path, '.json'), strlen('post-retraction-'));
                    $record = AcceptedRetraction::read($root, $id);
                    $before = trim(LabelRemoval::git($root, ['rev-parse', 'HEAD']));
                    if (RetractionDeletion::commit($root, $record) !== $before) $result['migrated'][] = $id;
                } catch (\Throwable $error) { $result['unresolved'][basename($path)] = $error->getMessage(); }
            }

            foreach (glob($root . '/records/post-retractions/*.txt') ?: [] as $path) {
                try {
                    $relative = 'records/post-retractions/' . basename($path);
                    $request = $repository->loadPostRetraction($relative);
                    [$sourcePath, $bytes] = LabelRemoval::source($root, $request->postId);
                    if (is_file($root . '/' . $sourcePath)) $post = $repository->loadPost($sourcePath);
                    else {
                        try { $post = (new PostRecordParser())->parse($bytes); }
                        catch (\ForumRewrite\Canonical\CanonicalRecordParseException $error) {
                            if ($error->getMessage() !== 'Missing required post header: Created-At') throw $error;
                            $date = \ForumRewrite\Canonical\LegacyPostTimestamp::resolve($root, $sourcePath, $bytes);
                            if ($date === null) throw $error;
                            $post = (new PostRecordParser())->parse(preg_replace('/\n/', "\nCreated-At: {$date}\n", $bytes, 1));
                        }
                    }
                    $record = AcceptedRetraction::prepare($post, $request, (string) file_get_contents($path),
                        is_file($path . '.asc') ? (string) file_get_contents($path . '.asc') : '', $request->attribution ?? $request->classification, hash('sha256', $bytes));
                    RetractionDeletion::commit($root, $record);
                    $result['migrated'][] = $request->postId;
                } catch (\Throwable $error) {
                    $result['unresolved'][basename($path)] = $error->getMessage();
                }
            }
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
