<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

final class RepositoryArchiveImportCommandTest
{
    public function testImportRepositoryArchiveSkipsDuplicatesAndSavesConflicts(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-import-repo-' . $suffix;
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-import-db-' . $suffix . '.sqlite3';
        $sourceParent = sys_get_temp_dir() . '/forum-rewrite-import-source-' . $suffix;
        $archivePath = sys_get_temp_dir() . '/forum-rewrite-import-archive-' . $suffix . '.tar.gz';
        $conflictDirectory = null;

        try {
            $this->runCommand(sprintf(
                'php %s %s',
                escapeshellarg(__DIR__ . '/../scripts/init_local_repository.php'),
                escapeshellarg($repositoryRoot),
            ));

            $sourceRoot = $sourceParent . '/local_repository';
            $this->ensureDirectory($sourceRoot . '/records/posts/2026/04/10');

            copy(
                $repositoryRoot . '/records/posts/root-001.txt',
                $sourceRoot . '/records/posts/2026/04/10/root-001.txt',
            );

            file_put_contents(
                $sourceRoot . '/records/posts/root-001.txt',
                "Post-ID: root-001\n"
                . "Board-Tags: general meta\n"
                . "Subject: Hello world\n"
                . "\n"
                . "First line preview.\n"
                . "Second line body.\n",
            );

            file_put_contents(
                $sourceRoot . '/records/posts/thread-import-test.txt',
                "Post-ID: thread-import-test\n"
                . "Created-At: 2026-08-26T03:00:00Z\n"
                . "Board-Tags: general\n"
                . "Subject: Imported test\n"
                . "\n"
                . "Imported body.\n",
            );

            $this->runCommand(sprintf(
                'tar -czf %s -C %s local_repository',
                escapeshellarg($archivePath),
                escapeshellarg($sourceParent),
            ));

            [$exitCode, $output] = $this->runCommandAllowFailure(sprintf(
                'php %s %s %s %s --no-commit',
                escapeshellarg(__DIR__ . '/../scripts/import_repository_archive.php'),
                escapeshellarg($archivePath),
                escapeshellarg($repositoryRoot),
                escapeshellarg($databasePath),
            ));

            assertSame(0, $exitCode);
            assertStringContains('Imported: 1', $output);
            assertStringContains('Duplicate canonical identity skipped: 1', $output);
            assertStringContains('Conflicts saved for review: 1', $output);
            assertTrue(is_file($repositoryRoot . '/records/posts/thread-import-test.txt'));

            $conflictDirectory = $this->outputValue($output, 'Conflict directory');
            assertTrue($conflictDirectory !== null);
            assertTrue(is_file($conflictDirectory . '/path/records/posts/root-001.txt'));
        } finally {
            $this->deleteDirectory($repositoryRoot);
            $this->deleteDirectory($sourceParent);
            if ($conflictDirectory !== null) {
                $this->deleteDirectory($conflictDirectory);
            }
            if (is_file($databasePath)) {
                unlink($databasePath);
            }
            if (is_file($archivePath)) {
                unlink($archivePath);
            }
        }
    }

    private function runCommand(string $command): string
    {
        [$exitCode, $output] = $this->runCommandAllowFailure($command);
        if ($exitCode !== 0) {
            throw new RuntimeException($output);
        }

        return $output;
    }

    /**
     * @return array{0:int,1:string}
     */
    private function runCommandAllowFailure(string $command): array
    {
        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }

    private function outputValue(string $output, string $key): ?string
    {
        foreach (explode("\n", $output) as $line) {
            if (str_starts_with($line, $key . ': ')) {
                return substr($line, strlen($key) + 2);
            }
        }

        return null;
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create directory: ' . $path);
        }
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($path);
    }
}
