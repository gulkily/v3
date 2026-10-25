<?php

declare(strict_types=1);
namespace ForumRewrite\Write;

use ForumRewrite\Canonical\AcceptedRetraction;
use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\LabelRemoval;
use RuntimeException;

/** Caller holds the ordinary repository write lock. The record is also the retry input. */
final class RetractionDeletion
{
    public static function commit(string $root, array $record): string
    {
        $path = AcceptedRetraction::path($record['post_id']);
        $bytes = AcceptedRetraction::encode($record);
        $paths = [$path];
        $before = [$path => is_file($root . '/' . $path) ? file_get_contents($root . '/' . $path) : null];
        $legacy = CanonicalPathResolver::postRetraction('post-retraction-' . $record['post_id']);
        foreach (array_merge(CanonicalPathResolver::postCandidates($record['post_id']), [$legacy]) as $candidate) {
            foreach ([$candidate, $candidate . '.asc'] as $remove) {
                if (is_link($root . '/' . $remove)) throw new RuntimeException('Cannot retract a symlink.');
                $tracked = trim(LabelRemoval::git($root, ['ls-files', '--', $remove])) !== '';
                if ($tracked || is_file($root . '/' . $remove)) {
                    $paths[] = $remove;
                    $before[$remove] = is_file($root . '/' . $remove) ? file_get_contents($root . '/' . $remove) : null;
                }
            }
        }
        if (!is_dir(dirname($root . '/' . $path))) mkdir(dirname($root . '/' . $path), 0775, true);
        try {
            if (file_put_contents($root . '/' . $path, $bytes) === false) throw new RuntimeException('Cannot save retraction.');
            foreach (array_slice($paths, 1) as $remove) {
                if (is_file($root . '/' . $remove) && !unlink($root . '/' . $remove)) throw new RuntimeException('Cannot remove retracted content.');
            }
            LabelRemoval::git($root, array_merge(['add', '-A', '--'], $paths));
            if (trim(LabelRemoval::git($root, array_merge(['diff', '--cached', '--name-only', '--'], $paths))) !== '') {
                LabelRemoval::git($root, array_merge(['-c', 'user.name=Forum Rewrite', '-c', 'user.email=forum-rewrite@example.invalid',
                    'commit', '-m', 'Retract post ' . $record['post_id'] . ' by ' . $record['actor'], '--'], $paths));
            }
        } catch (\Throwable $error) {
            foreach ($before as $relative => $original) {
                if ($original === null) { if (is_file($root . '/' . $relative)) unlink($root . '/' . $relative); }
                else file_put_contents($root . '/' . $relative, $original);
            }
            LabelRemoval::git($root, array_merge(['reset', '-q', 'HEAD', '--'], $paths));
            throw $error;
        }
        return trim(LabelRemoval::git($root, ['rev-parse', 'HEAD']));
    }
}
