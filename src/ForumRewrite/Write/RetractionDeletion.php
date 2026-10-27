<?php

declare(strict_types=1);
namespace ForumRewrite\Write;

use ForumRewrite\Canonical\{AcceptedRetraction, CanonicalPathResolver, LabelRemoval};
use RuntimeException;

/** Caller holds the write lock. Complete retained evidence also supplies interrupted retry. */
final class RetractionDeletion
{
    public static function commit(string $root, array $record, ?callable $checkpoint = null): string
    {
        $files = AcceptedRetraction::files($record);
        $existing = AcceptedRetraction::read($root, $record['post_id']);
        if ($existing !== null && $existing !== $record) throw new RuntimeException('Retraction already accepted with different evidence.');
        $remove = [];
        foreach (CanonicalPathResolver::postCandidates($record['post_id']) as $candidate) {
            foreach ([$candidate, $candidate . '.asc'] as $path) {
                if (is_link($root . '/' . $path)) throw new RuntimeException('Cannot retract a symlink.');
                if (is_file($root . '/' . $path) || trim(LabelRemoval::git($root, ['ls-files', '--', $path])) !== ''
                    || trim(LabelRemoval::git($root, ['ls-tree', '--name-only', 'HEAD', '--', $path])) !== '') $remove[] = $path;
            }
        }
        $paths = array_merge(array_keys($files), $remove);
        $before = [];
        foreach ($paths as $path) {
            $absolute = $root . '/' . $path;
            if (is_link($absolute) || is_link(dirname($absolute)) || (file_exists($absolute) && !is_file($absolute))) throw new RuntimeException('Unsafe retraction path.');
            $before[$path] = is_file($absolute) ? file_get_contents($absolute) : null;
        }
        try {
            // Receipt is installed last, before any deletion: interrupted writes never lose
            // a post unless the complete evidence needed to finish the commit is present.
            foreach ($files as $path => $bytes) {
                $absolute = $root . '/' . $path;
                if (!is_dir(dirname($absolute)) && !mkdir(dirname($absolute), 0775, true)) throw new RuntimeException('Cannot create retraction directory.');
                $temporary = tempnam(dirname($absolute), '.retraction-');
                if ($temporary === false) throw new RuntimeException('Cannot create retraction temporary file.');
                try {
                    if (file_put_contents($temporary, $bytes) !== strlen($bytes) || !rename($temporary, $absolute)) throw new RuntimeException('Cannot save retraction evidence.');
                } finally { if (is_file($temporary)) unlink($temporary); }
                if ($checkpoint !== null) $checkpoint('written', $path);
            }
            foreach ($remove as $path) {
                if (is_file($root . '/' . $path) && !unlink($root . '/' . $path)) throw new RuntimeException('Cannot remove retracted content.');
                if ($checkpoint !== null) $checkpoint('removed', $path);
            }
            $stagePaths = array_values(array_filter($paths, static fn (string $path): bool => is_file($root . '/' . $path)
                || trim(LabelRemoval::git($root, ['ls-files', '--', $path])) !== ''));
            LabelRemoval::git($root, array_merge(['add', '-A', '--'], $stagePaths));
            if ($checkpoint !== null) $checkpoint('staged', '');
            if (trim(LabelRemoval::git($root, array_merge(['diff', '--cached', '--name-only', '--'], $paths))) !== '') {
                LabelRemoval::git($root, array_merge(['-c', 'user.name=Forum Rewrite', '-c', 'user.email=forum-rewrite@example.invalid',
                    'commit', '-m', 'Retract post ' . $record['post_id'] . ' by ' . $record['actor'], '--'], $paths));
            }
        } catch (\Throwable $error) {
            foreach ($before as $path => $bytes) {
                if ($bytes === null) { if (is_file($root . '/' . $path)) unlink($root . '/' . $path); }
                else file_put_contents($root . '/' . $path, $bytes);
            }
            LabelRemoval::git($root, array_merge(['reset', '-q', 'HEAD', '--'], $paths));
            throw $error;
        }
        return trim(LabelRemoval::git($root, ['rev-parse', 'HEAD']));
    }
}
