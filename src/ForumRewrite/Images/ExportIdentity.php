<?php

declare(strict_types=1);
namespace ForumRewrite\Images;

use RuntimeException;

/** Content identity, captured while holding the instance writer lock. */
final class ExportIdentity
{
    public static function capture(string $repository, string $revision, array $inventory): string
    {
        ImageBackup::treeBytes($repository, excludeGit: true);
        $entries = new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($repository, \FilesystemIterator::SKIP_DOTS),
            static fn (\SplFileInfo $file): bool => $file->getFilename() !== '.git',
        );
        $files = [];
        foreach (new \RecursiveIteratorIterator($entries, \RecursiveIteratorIterator::SELF_FIRST) as $file) {
            if ($file->isLink() || (!$file->isDir() && !$file->isFile())) {
                throw new RuntimeException('Unsafe content in export source.');
            }
            $path = substr($file->getPathname(), strlen($repository) + 1);
            $files[$path] = [$file->isDir() ? 'directory' : self::digest($file->getPathname()), $file->getPerms() & 0777];
        }
        ksort($files);
        // Also invalidate on changes to the snapshot generators and bundled guide.
        $generators = [];
        foreach ([__FILE__, __DIR__ . '/ImageBackup.php', __DIR__ . '/RepositorySnapshot.php',
            __DIR__ . '/RepositoryArchiveReadme.php', __DIR__ . '/ImageReference.php',
            __DIR__ . '/../Canonical/CanonicalRecordRepository.php', __DIR__ . '/../Canonical/LegacyPostTimestamp.php',
            dirname(__DIR__, 3) . '/resources/repository-archive/README.md'] as $source) {
            $generators[] = self::digest($source);
        }
        return hash('sha256', json_encode(['export-v1', $revision, $files, $inventory, $generators], JSON_THROW_ON_ERROR));
    }

    private static function digest(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if ($hash === false) throw new RuntimeException('Cannot verify export source.');
        return $hash;
    }
}
