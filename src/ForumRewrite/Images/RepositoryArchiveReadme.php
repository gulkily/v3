<?php

declare(strict_types=1);

namespace ForumRewrite\Images;

use RuntimeException;

/** Adds recipient guidance only to the temporary export snapshot. */
final class RepositoryArchiveReadme
{
    public static function write(string $snapshotRoot): void
    {
        $guide = @file_get_contents(dirname(__DIR__, 3) . '/resources/repository-archive/README.md');
        if ($guide === false) {
            throw new RuntimeException('Repository download guide is missing; repair the application files and retry.');
        }
        $path = $snapshotRoot . '/README.md';
        if (file_exists($path) || is_link($path)) {
            if (!is_file($path) || is_link($path) || ($original = @file_get_contents($path)) === false) {
                throw new RuntimeException('Cannot preserve repository README.md; repair the file and retry.');
            }
            if ($original === $guide) { return; }
            // Repeated preparation of an already prepared snapshot is harmless.
            $suffix = substr($original, strlen($guide));
            if (str_starts_with($original, $guide)
                && preg_match('/\A\nOriginal repository notes: \[(README\.repository(?:-[0-9]+)?\.md)\]\(\1\)\.\n\z/', $suffix, $match)
                && is_file($snapshotRoot . '/' . $match[1])) {
                return;
            }
            $name = 'README.repository.md';
            for ($number = 2; file_exists($snapshotRoot . '/' . $name) || is_link($snapshotRoot . '/' . $name); $number++) {
                $name = 'README.repository-' . $number . '.md';
            }
            if (!@rename($path, $snapshotRoot . '/' . $name)) {
                throw new RuntimeException('Cannot preserve repository notes; check temporary storage and retry.');
            }
            $guide .= "\nOriginal repository notes: [{$name}]({$name}).\n";
        }
        if (@file_put_contents($path, $guide) !== strlen($guide)) {
            throw new RuntimeException('Cannot write repository download guide; check temporary storage and retry.');
        }
    }
}
