<?php

declare(strict_types=1);
namespace ForumRewrite\Images;

use RuntimeException;

/** Bounded snapshots; never streams a partially assembled archive. */
final class ImageBackup
{
    public function __construct(private readonly string $repositoryRoot, private readonly string $databasePath, private readonly ?string $projectRoot = null) {}

    public function revision(): string
    {
        return $this->git(['rev-parse', 'HEAD']);
    }

    public function export(string $kind, ?string $expectedRevision = null, string $imageFormat = 'zip'): array
    {
        if (!in_array($kind, ['images', 'tar.gz', 'zip'], true)) throw new RuntimeException('Unsupported backup format.');
        if (!in_array($imageFormat, ['zip', 'tar'], true)) throw new RuntimeException('Unsupported image backup format.');
        if ($kind === 'images' && $imageFormat === 'zip' && !class_exists(\ZipArchive::class)) {
            throw new RuntimeException('PHP ZIP support is unavailable. Enable the zip extension for the web PHP runtime and retry.');
        }
        if ($kind === 'images' && $imageFormat === 'zip') $kind = 'images.zip';
        $cache = new ArchiveCache(dirname($this->databasePath), $this->repositoryRoot);
        $locks = new ArchiveLocks(dirname($this->databasePath));
        // Never hold the writer lock while waiting for the builder. Hits need no build lock.
        $hit = $locks->withExclusiveLock('forum-rewrite.lock', fn () => $cache->open($this->identity($expectedRevision, $kind), $kind));
        if ($hit !== null) return $hit;
        return $locks->withExclusiveLock('image-export.lock', function () use ($cache, $locks, $kind, $expectedRevision): array {
            $hit = $locks->withExclusiveLock('forum-rewrite.lock',
                fn () => $cache->open($this->identity($expectedRevision, $kind), $kind),
            );
            if ($hit !== null) return $hit;
            $built = $this->build($kind, $expectedRevision, $locks, $cache);
            try {
                return $locks->withExclusiveLock('forum-rewrite.lock', function () use ($cache, $built, $kind, $expectedRevision): array {
                    $identity = $this->identity($expectedRevision, $kind);
                    if ($identity !== $built['identity']) throw new ArchiveConflictException('Repository changed during backup; refresh Backup and retry.');
                    return $cache->publish($built, $identity, $kind);
                });
            } finally {
                self::removeTree($built['workspace']);
            }
        });
    }

    private function build(string $kind, ?string $expectedRevision, ArchiveLocks $locks, ArchiveCache $cache): array
    {
        $work = $workspace = $archive = null;
        $extension = $kind === 'images.zip' ? 'zip' : ($kind === 'images' ? 'tar' : $kind);
        try {
            $identity = null;
            $revision = $locks->withExclusiveLock('forum-rewrite.lock', function () use ($kind, $expectedRevision, $cache, $extension, &$work, &$workspace, &$archive, &$identity): string {
                $revision = $this->revision();
                if ($expectedRevision !== null && !hash_equals($revision, $expectedRevision)) {
                    throw new ArchiveConflictException('Repository changed. Refresh Backup and download both artifacts again.');
                }
                if ($this->git(['status', '--porcelain', '--', 'records']) !== '') {
                    throw new RuntimeException('Canonical records have uncommitted changes; repair before backup.');
                }
                $store = new ImageStore($this->repositoryRoot);
                $inventory = $store->inventory();
                $scores = $this->scores($kind);
                $identity = $this->bindScores(ExportIdentity::capture($this->repositoryRoot, $revision, $inventory), $scores);
                $source = in_array($kind, ['images', 'images.zip'], true) ? $store->root : $this->repositoryRoot;
                $size = in_array($kind, ['images', 'images.zip'], true) ? array_sum(array_column($inventory, 'bytes')) : self::treeBytes($source, excludeGit: true);
                $workspace = $cache->prepareBuild($identity, $size + strlen($scores ?? ''));
                $work = $workspace . '/work';
                $archive = $workspace . '/archive.' . $extension;
                if (!mkdir($work, 0700)) throw new RuntimeException('Cannot prepare snapshot workspace.');
                if (in_array($kind, ['images', 'images.zip'], true)) {
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
                    // Option B: stage the committed tree from git's object store via
                    // `git archive | tar -x` instead of a recursive working-tree copy.
                    // Committed-content-only (no .git, no untracked files); the existing
                    // injection (image-reference resolution + legacy sidecars, snapshot
                    // manifest, recipient README override) then runs against the staged
                    // tree exactly as before.
                    mkdir($work . '/repository', 0700);
                    self::stageCommittedTree($this->repositoryRoot, $revision, $work . '/repository');
                    foreach (self::references($source, $work . '/repository') as $ref) { $store->resolve($ref); }
                    RepositorySnapshot::write($work . '/repository', $revision);
                    RepositoryArchiveReadme::write($work . '/repository');
                    $scoreFile = $work . '/repository/' . \ForumRewrite\Scoring\FastmodScoreTransfer::PATH;
                    if (is_link($scoreFile) || is_dir($scoreFile)) throw new RuntimeException('Invalid reserved Fastmod supplement path.');
                    if (file_put_contents($scoreFile, $scores) === false) throw new RuntimeException('Cannot write Fastmod supplement.');
                    self::treeBytes($work . '/repository');
                }
                if ($this->revision() !== $revision || $this->git(['status', '--porcelain', '--', 'records']) !== '') {
                    throw new ArchiveConflictException('Repository changed during backup; retry after other writers finish.');
                }
                return $revision;
            });
            if ($kind === 'images.zip') {
                $this->writeImageZip($work, $archive);
            } elseif ($kind === 'images') {
                $tar = new \PharData($archive);
                $tar->addFile($work . '/manifest.json', 'manifest.json');
                foreach (glob($work . '/objects/*') ?: [] as $path) { $tar->addFile($path, 'objects/' . basename($path)); }
                unset($tar);
            } else {
                // The committed tree is already staged under $work/repository (via
                // git archive | tar -x) with generated files injected; archive it.
                $command = $kind === 'zip'
                    ? 'cd ' . escapeshellarg($work) . ' && zip -qr ' . escapeshellarg($archive) . ' repository'
                    : 'tar -czf ' . escapeshellarg($archive) . ' -C ' . escapeshellarg($work) . ' repository';
                exec($command . ' 2>&1', $output, $status);
                if ($status !== 0 || !is_file($archive)) { throw new RuntimeException('Unable to assemble complete backup.'); }
            }
            $locks->withExclusiveLock('forum-rewrite.lock', function () use ($identity, $kind): void {
                if ($this->identity(null, $kind) !== $identity) throw new ArchiveConflictException('Repository changed during backup; refresh Backup and retry.');
            });
            chmod($archive, 0600);
            return ['path' => $archive, 'revision' => $revision, 'extension' => $extension, 'identity' => $identity, 'workspace' => $workspace];
        } catch (\Throwable $error) {
            if ($workspace !== null) self::removeTree($workspace);
            if ($error instanceof ArchiveBusyException || $error instanceof ArchiveConflictException) throw $error;
            throw new RuntimeException('Backup failed: ' . $error->getMessage(), 0, $error);
        } finally {
            if ($work !== null) self::removeTree($work);
        }
    }

    /**
     * Stage the committed tree at $revision into $target from git's object store.
     * Uses `git archive -o <tmp>.tar` then extracts via PharData — deliberately no
     * `tar` shell-out, so the only external `tar` remains the compression step.
     * Committed-content-only (no .git, no untracked files); no recursive working-
     * tree copy. The caller then injects generated files into $target.
     */
    private static function stageCommittedTree(string $repository, string $revision, string $target): void
    {
        $tarPath = $target . '/.committed-tree.tar';
        $command = 'git -C ' . escapeshellarg($repository) . ' archive --format=tar -o ' . escapeshellarg($tarPath)
            . ' ' . escapeshellarg($revision);
        exec($command . ' 2>&1', $output, $status);
        if ($status !== 0 || !is_file($tarPath)) {
            throw new RuntimeException('Unable to stage committed content for backup.');
        }
        try {
            $phar = new \PharData($tarPath);
            $phar->extractTo($target, null, true);
            unset($phar);
        } catch (\Throwable $error) {
            @unlink($tarPath);
            throw new RuntimeException('Unable to expand committed content for backup.');
        }
        if (!unlink($tarPath)) {
            throw new RuntimeException('Unable to clean staged archive.');
        }
        // git archive omits empty directories; the snapshot/export contract still
        // expects records/ to exist even when all posts have been removed. The old
        // working-tree copy preserved the empty directory, so recreate it here.
        if (!is_dir($target . '/records') && !mkdir($target . '/records', 0700, true)) {
            throw new RuntimeException('Unable to prepare records directory for backup.');
        }
    }

    /** Caller holds the instance writer lock. */
    private function identity(?string $expectedRevision = null, string $kind = 'tar.gz'): string
    {
        $revision = $this->revision();
        if ($expectedRevision !== null && !hash_equals($revision, $expectedRevision)) {
            throw new ArchiveConflictException('Repository changed. Refresh Backup and download both artifacts again.');
        }
        if ($this->git(['status', '--porcelain', '--', 'records']) !== '') {
            throw new RuntimeException('Canonical records have uncommitted changes; repair before backup.');
        }
        return $this->bindScores(ExportIdentity::capture($this->repositoryRoot, $revision, (new ImageStore($this->repositoryRoot))->identityListing()), $this->scores($kind));
    }

    private function scores(string $kind): ?string
    {
        if (in_array($kind, ['images', 'images.zip'], true)) return null;
        $project = $this->projectRoot ?? dirname(__DIR__, 3);
        $path = \ForumRewrite\Scoring\FastScoreDatabaseConfig::path($project, \ForumRewrite\Support\PrivateConfig::load($project));
        return \ForumRewrite\Scoring\FastmodScoreSnapshot::capture($this->repositoryRoot, $path);
    }

    private function bindScores(string $identity, ?string $scores): string
    {
        return $scores === null ? $identity : hash('sha256', $identity . ':fastmod-scores-v1:' . hash('sha256', $scores));
    }

    private function writeImageZip(string $work, string $archive): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($archive, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Unable to create image ZIP. Check temporary storage and retry.');
        }
        try {
            if (!$zip->addFile($work . '/manifest.json', 'manifest.json') || !$zip->addEmptyDir('objects')) {
                throw new RuntimeException('Unable to add image ZIP inventory.');
            }
            foreach (glob($work . '/objects/*') ?: [] as $path) {
                $name = 'objects/' . basename($path);
                if (!$zip->addFile($path, $name) || !$zip->setCompressionName($name, \ZipArchive::CM_STORE)) {
                    throw new RuntimeException('Unable to add image to ZIP.');
                }
            }
        } catch (\Throwable $error) {
            $zip->close();
            throw $error;
        }
        if (!$zip->close() || !is_file($archive)) {
            throw new RuntimeException('Unable to finish image ZIP. Check temporary storage and retry.');
        }
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
