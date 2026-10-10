<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Import\RepositoryArchive;

final class RepositoryArchiveValidationTest
{
    public function testArbitraryRepositoryNameAndHistoryExclusion(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('renamed-source', true);
        $w->git($source);
        $archive = $w->root . '/source.tar.gz';
        assertSame(0, $w->command(['tar', '-czf', $archive, '-C', $w->root, 'renamed-source'])[0]);
        $result = (new RepositoryArchive())->extract($archive, $w->root . '/extracted');
        assertTrue(is_file($result['root'] . '/records/posts/root-001.txt'));
        assertTrue(!is_dir($result['root'] . '/.git'));
        assertTrue($result['excluded_archive_categories']['.git'] > 0);
    }

    public function testUnsafeAndAmbiguousArchivesAreRejectedBeforeExtraction(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source', true);
        symlink('/tmp', $source . '/escape');
        $archive = $w->root . '/source.tar.gz';
        $w->command(['tar', '-czf', $archive, '-C', $w->root, 'source']);
        $this->reject($archive, $w->root . '/out');
        unlink($source . '/escape');
        $w->repository('second', true);
        $w->command(['tar', '-czf', $archive, '-C', $w->root, 'source', 'second']);
        $this->reject($archive, $w->root . '/out');
        $w->command(['tar', '-czf', $archive, '--transform=s|source|../escape|', '-C', $w->root, 'source']);
        $this->reject($archive, $w->root . '/out');
    }

    public function testTruncationAndLimitsAreRejected(): void
    {
        $w = new ImportTestWorkspace();
        $w->repository('source', true);
        $archive = $w->root . '/source.tar.gz';
        $w->command(['tar', '-czf', $archive, '-C', $w->root, 'source']);
        $this->reject($archive, $w->root . '/out', new RepositoryArchive(maxExpandedBytes: 1024));
        $this->reject($archive, $w->root . '/out', new RepositoryArchive(maxEntries: 2));
        $this->reject($archive, $w->root . '/out', new RepositoryArchive(maxCompressedBytes: 10));
        file_put_contents($archive, substr(file_get_contents($archive), 0, -8));
        $this->reject($archive, $w->root . '/out');
    }

    private function reject(string $archive, string $directory, ?RepositoryArchive $reader = null): void
    {
        $failed = false;
        try {
            ($reader ?? new RepositoryArchive())->extract($archive, $directory);
        } catch (RuntimeException) {
            $failed = true;
        }
        assertTrue($failed);
        assertTrue(!file_exists($directory));
    }
}
