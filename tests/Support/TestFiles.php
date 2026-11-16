<?php

declare(strict_types=1);

/** File helpers for tests and fixtures. */
final class TestFiles
{
    /** Copies a directory tree, refusing symlinks, keeping file modes. */
    public static function copyTree(string $source, string $target): void
    {
        if (!mkdir($target, 0700, true)) {
            throw new RuntimeException('Unable to create ' . $target);
        }
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($entries as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Fixture cannot contain symlinks: ' . $file->getPathname());
            }
            $destination = $target . substr($file->getPathname(), strlen($source));
            if ($file->isDir()) {
                if (!mkdir($destination, 0700)) {
                    throw new RuntimeException('Unable to create ' . $destination);
                }
            } elseif (!copy($file->getPathname(), $destination) || !chmod($destination, $file->getPerms() & 0777)) {
                throw new RuntimeException('Unable to copy ' . $file->getPathname());
            }
        }
    }
}
