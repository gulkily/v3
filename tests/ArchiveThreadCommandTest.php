<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

final class ArchiveThreadCommandTest
{
    public function testArchiveThreadCommandCreatesZipRemovesRecordsAndRefreshesPublicState(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createEnvironment();
        $archivePath = sys_get_temp_dir() . '/forum-rewrite-thread-archive-' . bin2hex(random_bytes(6)) . '.zip';

        $this->writePostReaction($repositoryRoot);
        $this->runCommand(sprintf(
            'php %s %s %s %s',
            escapeshellarg(__DIR__ . '/../scripts/build_static_artifacts.php'),
            escapeshellarg($repositoryRoot),
            escapeshellarg($databasePath),
            escapeshellarg($artifactRoot),
        ));
        assertTrue(is_file($artifactRoot . '/threads/root-001.html'));
        assertTrue(is_file($artifactRoot . '/posts/root-001.html'));
        assertTrue(is_file($artifactRoot . '/posts/reply-001.html'));

        [$exitCode, $output] = $this->runCommandAllowFailure(sprintf(
            'php %s %s %s %s %s %s',
            escapeshellarg(__DIR__ . '/../scripts/archive_thread.php'),
            escapeshellarg('root-001'),
            escapeshellarg($repositoryRoot),
            escapeshellarg($databasePath),
            escapeshellarg($artifactRoot),
            escapeshellarg($archivePath),
        ));

        assertSame(0, $exitCode);
        assertStringContains('Archived thread and removed live canonical records.', $output);
        assertStringContains('Files archived: 4', $output);
        assertStringContains('Read model and static artifacts refreshed.', $output);
        assertTrue(is_file($archivePath));

        $manifest = $this->readArchiveManifest($archivePath);
        assertSame('thread_archive', $manifest['kind'] ?? null);
        assertSame('root-001', $manifest['thread_id'] ?? null);
        assertTrue(is_string($manifest['source_commit'] ?? null));

        $archivePaths = $this->archivePaths($archivePath);
        assertTrue(in_array('manifest.json', $archivePaths, true));
        assertTrue(in_array('records/posts/root-001.txt', $archivePaths, true));
        assertTrue(in_array('records/posts/reply-001.txt', $archivePaths, true));
        assertTrue(in_array('records/thread-labels/thread-label-20260415153000-ab12cd34.txt', $archivePaths, true));
        assertTrue(in_array('records/post-reactions/post-reaction-archive-test.txt', $archivePaths, true));

        assertFalse(is_file($repositoryRoot . '/records/posts/root-001.txt'));
        assertFalse(is_file($repositoryRoot . '/records/posts/reply-001.txt'));
        assertFalse(is_file($repositoryRoot . '/records/thread-labels/thread-label-20260415153000-ab12cd34.txt'));
        assertFalse(is_file($repositoryRoot . '/records/post-reactions/post-reaction-archive-test.txt'));

        assertFalse(is_file($artifactRoot . '/threads/root-001.html'));
        assertFalse(is_file($artifactRoot . '/posts/root-001.html'));
        assertFalse(is_file($artifactRoot . '/posts/reply-001.html'));

        $pdo = new PDO('sqlite:' . $databasePath);
        assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM threads WHERE root_post_id = 'root-001'")->fetchColumn());
        assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE thread_id = 'root-001'")->fetchColumn());

        [$logExitCode, $logOutput] = $this->runCommandAllowFailure(
            'git -C ' . escapeshellarg($repositoryRoot) . ' log --oneline -1'
        );
        assertSame(0, $logExitCode);
        assertStringContains('Archive thread root-001', $logOutput);
    }

    public function testArchiveThreadCommandRejectsMissingThread(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createEnvironment();
        $archivePath = sys_get_temp_dir() . '/forum-rewrite-thread-archive-missing-' . bin2hex(random_bytes(6)) . '.zip';

        [$exitCode, $output] = $this->runCommandAllowFailure(sprintf(
            'php %s %s %s %s %s %s',
            escapeshellarg(__DIR__ . '/../scripts/archive_thread.php'),
            escapeshellarg('missing-thread'),
            escapeshellarg($repositoryRoot),
            escapeshellarg($databasePath),
            escapeshellarg($artifactRoot),
            escapeshellarg($archivePath),
        ));

        assertSame(1, $exitCode);
        assertStringContains('Thread root post does not exist: missing-thread', $output);
        assertFalse(is_file($archivePath));
    }

    public function testArchiveThreadCommandRejectsReplyId(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createEnvironment();
        $archivePath = sys_get_temp_dir() . '/forum-rewrite-thread-archive-reply-' . bin2hex(random_bytes(6)) . '.zip';

        [$exitCode, $output] = $this->runCommandAllowFailure(sprintf(
            'php %s %s %s %s %s %s',
            escapeshellarg(__DIR__ . '/../scripts/archive_thread.php'),
            escapeshellarg('reply-001'),
            escapeshellarg($repositoryRoot),
            escapeshellarg($databasePath),
            escapeshellarg($artifactRoot),
            escapeshellarg($archivePath),
        ));

        assertSame(1, $exitCode);
        assertStringContains('thread_id must refer to a root thread, not a reply: reply-001', $output);
        assertFalse(is_file($archivePath));
        assertTrue(is_file($repositoryRoot . '/records/posts/reply-001.txt'));
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function createEnvironment(): array
    {
        $suffix = bin2hex(random_bytes(6));
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-archive-repo-' . $suffix;
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-archive-db-' . $suffix . '.sqlite3';
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-archive-artifacts-' . $suffix;

        $this->runCommand(sprintf(
            'php %s %s',
            escapeshellarg(__DIR__ . '/../scripts/init_local_repository.php'),
            escapeshellarg($repositoryRoot),
        ));

        return [$repositoryRoot, $databasePath, $artifactRoot];
    }

    private function writePostReaction(string $repositoryRoot): void
    {
        if (!is_dir($repositoryRoot . '/records/post-reactions') && !mkdir($repositoryRoot . '/records/post-reactions', 0777, true) && !is_dir($repositoryRoot . '/records/post-reactions')) {
            throw new RuntimeException('Unable to create post reaction fixture directory.');
        }

        $path = $repositoryRoot . '/records/post-reactions/post-reaction-archive-test.txt';
        $contents = "Record-ID: post-reaction-archive-test\n"
            . "Created-At: 2026-04-15T15:30:00Z\n"
            . "Post-ID: reply-001\n"
            . "Operation: add\n"
            . "Tags: flag\n"
            . "Author-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n"
            . "Reason: Archive command test\n\n";

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to write post reaction fixture.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readArchiveManifest(string $archivePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Unable to open archive.');
        }

        try {
            $manifest = $zip->getFromName('manifest.json');
            if (!is_string($manifest)) {
                throw new RuntimeException('Archive manifest is missing.');
            }

            $decoded = json_decode($manifest, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new RuntimeException('Archive manifest is invalid.');
            }

            return $decoded;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function archivePaths(string $archivePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Unable to open archive.');
        }

        try {
            $paths = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (is_string($name)) {
                    $paths[] = $name;
                }
            }

            sort($paths);

            return $paths;
        } finally {
            $zip->close();
        }
    }

    private function runCommand(string $command): void
    {
        [$exitCode, $output] = $this->runCommandAllowFailure($command);
        if ($exitCode !== 0) {
            throw new RuntimeException('Command failed: ' . $output);
        }
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
}

if (!function_exists('assertFalse')) {
    function assertFalse(bool $condition): void
    {
        if ($condition) {
            throw new RuntimeException('Failed asserting that condition is false.');
        }
    }
}

if (!function_exists('assertStringContains')) {
    function assertStringContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that output contains: ' . $needle);
        }
    }
}
