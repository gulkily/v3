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
        if (is_link($path) || (file_exists($path) && !is_file($path))) {
            throw new RuntimeException('Cannot replace repository README.md; repair the file and retry.');
        }
        if (@file_put_contents($path, $guide) !== strlen($guide)) {
            throw new RuntimeException('Cannot write repository download guide; check temporary storage and retry.');
        }
    }
}
