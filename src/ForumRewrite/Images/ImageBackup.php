<?php

declare(strict_types=1);
namespace ForumRewrite\Images;

use ForumRewrite\Support\ExecutionLock;
use RuntimeException;

/** Bounded snapshots; never streams a partially assembled archive. */
final class ImageBackup
{
    public function __construct(private readonly string $repositoryRoot, private readonly string $databasePath) {}

    public function revision(): string
    {
        return $this->git(['rev-parse', 'HEAD']);
    }

    public function export(string $kind, ?string $expectedRevision = null): array
    {
        if (!in_array($kind, ['images', 'tar.gz', 'zip'], true)) { throw new RuntimeException('Unsupported backup format.'); }
        return (new ExecutionLock(dirname($this->databasePath) . '/image-export.lock', 0))->withExclusiveLock(function () use ($kind, $expectedRevision): array {
            $work = sys_get_temp_dir() . '/forum-image-export-' . bin2hex(random_bytes(10));
            if (!mkdir($work, 0700)) { throw new RuntimeException('Unable to create backup workspace.'); }
            $extension = $kind === 'images' ? 'tar' : $kind;
            $archive = $work . '.' . $extension;
            try {
                $revision = (new ExecutionLock(dirname($this->databasePath) . '/forum-rewrite.lock'))->withExclusiveLock(function () use ($kind, $expectedRevision, $work): string {
                    $revision = $this->revision();
                    if ($expectedRevision !== null && !hash_equals($revision, $expectedRevision)) {
                        throw new RuntimeException('Repository changed. Refresh Backup and download both artifacts again.');
                    }
                    if ($this->git(['status', '--porcelain', '--', 'records']) !== '') {
                        throw new RuntimeException('Canonical records have uncommitted changes; repair before backup.');
                    }
                    $store = new ImageStore($this->repositoryRoot);
                    $inventory = $store->inventory();
                    $source = $kind === 'images' ? $store->root : $this->repositoryRoot;
                    $size = $kind === 'images' ? array_sum(array_column($inventory, 'bytes')) : self::treeBytes($source, excludeGit: true);
                    $free = disk_free_space($work);
                    if ($free === false || $free < 2 * $size + 16 * 1024 * 1024) { throw new RuntimeException('Insufficient temporary disk for complete backup; free space and retry.'); }
                    if ($kind === 'images') {
                        foreach (self::references($this->repositoryRoot) as $ref) { $store->resolve($ref); }
                        mkdir($work . '/objects', 0700);
                        foreach ($inventory as $name => $ref) {
                            if (!copy($store->root . '/' . $name, $work . '/objects/' . $name)
                                || hash_file('sha256', $work . '/objects/' . $name) !== $ref['sha256']) {
                                throw new RuntimeException('Image changed or could not be copied; backup incomplete.');
                            }
                        }
                        $manifest = ['schema' => 'forum-images-v1', 'repository_head' => $revision, 'created_at' => gmdate('c'), 'objects' => $inventory];
                        if (file_put_contents($work . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n") === false) { throw new RuntimeException('Unable to write backup inventory.'); }
                    } else {
                        self::copyTree($source, $work . '/repository', excludeGit: true);
                        foreach (self::references($source, $work . '/repository') as $ref) { $store->resolve($ref); }
                        RepositorySnapshot::write($work . '/repository', $revision);
                        self::treeBytes($work . '/repository');
                    }
                    if ($this->revision() !== $revision || $this->git(['status', '--porcelain', '--', 'records']) !== '') {
                        throw new RuntimeException('Repository changed during backup; retry after other writers finish.');
                    }
                    return $revision;
                });
                if ($kind === 'images') {
                    $tar = new \PharData($archive);
                    $tar->addFile($work . '/manifest.json', 'manifest.json');
                    foreach (glob($work . '/objects/*') ?: [] as $path) { $tar->addFile($path, 'objects/' . basename($path)); }
                    unset($tar);
                } else {
                    $command = $kind === 'zip'
                        ? 'cd ' . escapeshellarg($work) . ' && zip -qr ' . escapeshellarg($archive) . ' repository'
                        : 'tar -czf ' . escapeshellarg($archive) . ' -C ' . escapeshellarg($work) . ' repository';
                    exec($command . ' 2>&1', $output, $status);
                    if ($status !== 0 || !is_file($archive)) { throw new RuntimeException('Unable to assemble complete backup.'); }
                }
                chmod($archive, 0600);
                return ['path' => $archive, 'revision' => $revision, 'extension' => $extension];
            } catch (\Throwable $error) {
                @unlink($archive);
                throw new RuntimeException('Backup failed: ' . $error->getMessage(), 0, $error);
            } finally {
                self::removeTree($work);
            }
        });
    }

    public static function references(string $root, ?string $snapshotRoot = null): array
    {
        $refs = [];
        if (!is_dir($root . '/records/posts')) { return $refs; }
        $paths = []; $legacyPaths = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/records/posts', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isLink()) { throw new RuntimeException('Symlink in canonical post records.'); }
            if ($file->isFile() && $file->getExtension() === 'txt') {
                $path = substr($file->getPathname(), strlen(rtrim($root, '/')) + 1);
                $paths[] = $path;
                $contents = file_get_contents($file->getPathname());
                if ($contents === false) throw new RuntimeException('Cannot read post for content export: ' . $path);
                if (self::needsLegacyDate($contents)
                    && !file_exists($root . '/' . \ForumRewrite\Canonical\LegacyPostTimestamp::path($file->getBasename('.txt')))) {
                    $legacyPaths[] = $path;
                }
            }
        }
        $repository = new \ForumRewrite\Canonical\CanonicalRecordRepository($root, allowLegacyFilesystemTimestamp: false);
        $repository->preloadLegacyPostCreatedAts($legacyPaths);
        foreach ($paths as $path) {
            try {
                $record = $repository->loadPost($path);
                if ($snapshotRoot !== null) {
                    $contents = (string) file_get_contents($root . '/' . $path);
                    if (self::needsLegacyDate($contents)) {
                        $metadata = $snapshotRoot . '/' . \ForumRewrite\Canonical\LegacyPostTimestamp::path($record->postId);
                        if (!is_dir(dirname($metadata)) && !mkdir(dirname($metadata), 0700, true)) throw new RuntimeException('Cannot preserve legacy post dates.');
                        if (file_put_contents($metadata, \ForumRewrite\Canonical\LegacyPostTimestamp::encode($record->postId, $contents, $record->createdAt)) === false) throw new RuntimeException('Cannot preserve legacy post date.');
                    }
                }
                $ref = ImageReference::fromBody($record->body);
                if ($ref !== null) { $refs[ImageReference::filename($ref)] = $ref; }
            } catch (RuntimeException $error) {
                throw new RuntimeException($path . ': ' . $error->getMessage(), 0, $error);
            }
        }
        return $refs;
    }

    private static function needsLegacyDate(string $contents): bool
    {
        $headers = explode("\n\n", str_replace("\r\n", "\n", $contents), 2)[0];
        return preg_match('/^Created-At:/m', $headers) !== 1;
    }

    private function git(array $args): string
    {
        $command = 'git -C ' . escapeshellarg($this->repositoryRoot);
        foreach ($args as $arg) { $command .= ' ' . escapeshellarg($arg); }
        exec($command . ' 2>&1', $output, $status);
        if ($status !== 0) { throw new RuntimeException('Unable to verify repository for image backup.'); }
        return trim(implode("\n", $output));
    }

    public static function treeBytes(string $root, bool $excludeGit = false): int
    {
        $size = 0; $count = 0;
        foreach (new \RecursiveIteratorIterator(self::entries($root, $excludeGit)) as $file) {
            if ($file->isLink() || !$file->isFile()) { throw new RuntimeException('Backup source contains links or special files.'); }
            $size += $file->getSize(); $count++;
            if ($size > 512 * 1024 * 1024 || $count > 100000) { throw new RuntimeException('Repository exceeds bounded web backup capacity (512 MiB / 100000 files); use operator filesystem backup.'); }
        }
        return $size;
    }

    public static function copyTree(string $source, string $target, bool $excludeGit = false): void
    {
        if (!mkdir($target, 0700, true)) { throw new RuntimeException('Unable to create snapshot directory.'); }
        foreach (new \RecursiveIteratorIterator(self::entries($source, $excludeGit), \RecursiveIteratorIterator::SELF_FIRST) as $file) {
            if ($file->isLink()) { throw new RuntimeException('Snapshot cannot contain symlinks.'); }
            $destination = $target . substr($file->getPathname(), strlen($source));
            if ($file->isDir()) { if (!mkdir($destination, 0700)) { throw new RuntimeException('Unable to copy snapshot directory.'); } }
            elseif (!$file->isFile() || !copy($file->getPathname(), $destination) || !chmod($destination, $file->getPerms() & 0777)) { throw new RuntimeException('Unable to copy snapshot file.'); }
        }
    }

    private static function entries(string $root, bool $excludeGit): \RecursiveIterator
    {
        $entries = new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS);
        return $excludeGit ? new \RecursiveCallbackFilterIterator($entries, static fn (\SplFileInfo $file): bool => $file->getFilename() !== '.git') : $entries;
    }

    public static function removeTree(string $root): void
    {
        if (!is_dir($root)) { return; }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            if ($file->isDir() && !$file->isLink()) { rmdir($file->getPathname()); } else { unlink($file->getPathname()); }
        }
        rmdir($root);
    }
}
