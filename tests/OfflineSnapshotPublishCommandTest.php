<?php

declare(strict_types=1);

final class OfflineSnapshotPublishCommandTest
{
    public function testPublishBuildsOnlyIndependentOfflineSnapshot(): void
    {
        [$repositoryRoot, $databasePath, $staticHtmlRoot] = $this->createEnvironment();
        try {
            [$exitCode, $stdout, $stderr] = $this->runCommand($repositoryRoot, $databasePath, $staticHtmlRoot);

            assertSame(0, $exitCode);
            assertSame('', $stderr);
            assertStringContains('Offline snapshot published', $stdout);
            assertStringContains('does not rebuild data or render static post pages', $stdout);
            assertTrue(is_file($staticHtmlRoot . '/offline/snapshot.sqlite3'));
            assertFalse(file_exists($staticHtmlRoot . '/current'));
        } finally {
            $this->removeTree(dirname($repositoryRoot));
            @unlink($databasePath);
            $this->removeTree($staticHtmlRoot);
        }
    }

    public function testPublishRefusesApprovedMembersOnlyMode(): void
    {
        [$repositoryRoot, $databasePath, $staticHtmlRoot] = $this->createEnvironment();
        try {
            [$exitCode, $stdout, $stderr] = $this->runCommand($repositoryRoot, $databasePath, $staticHtmlRoot, true);

            assertSame(2, $exitCode);
            assertSame('', $stdout);
            assertStringContains('approved-members-only is enabled', $stderr);
            assertFalse(is_file($staticHtmlRoot . '/offline/snapshot.sqlite3'));
        } finally {
            $this->removeTree(dirname($repositoryRoot));
            @unlink($databasePath);
            $this->removeTree($staticHtmlRoot);
        }
    }

    /** @return array{0:string,1:string,2:string} */
    private function createEnvironment(): array
    {
        $suffix = bin2hex(random_bytes(6));
        $base = sys_get_temp_dir() . '/forum-offline-publish-command-' . $suffix;
        $repositoryRoot = $base . '/repository';
        $databasePath = $base . '/read-model.sqlite3';
        $staticHtmlRoot = $base . '/static_html';
        mkdir($repositoryRoot . '/records', 0700, true);
        $this->createSource($databasePath);

        return [$repositoryRoot, $databasePath, $staticHtmlRoot];
    }

    private function createSource(string $path): void
    {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('CREATE TABLE threads (root_post_id TEXT PRIMARY KEY, root_post_created_at TEXT, last_activity_at TEXT, subject TEXT, body_preview TEXT, board_tags_json TEXT, thread_labels_json TEXT, score_total INTEGER)');
        $pdo->exec('CREATE TABLE posts (post_id TEXT PRIMARY KEY, created_at TEXT, thread_id TEXT, parent_id TEXT, subject TEXT, body TEXT, board_tags_json TEXT, thread_type TEXT, author_label TEXT, author_profile_slug TEXT, sequence_number INTEGER, is_hidden INTEGER)');
        $pdo->exec("INSERT INTO threads VALUES ('root', '2026-01-01T00:00:00Z', '2026-01-01T00:00:00Z', 'Subject', 'Preview', '[]', '[]', 0)");
        $pdo->exec("INSERT INTO posts VALUES ('root', '2026-01-01T00:00:00Z', 'root', NULL, 'Subject', 'Body', '[]', NULL, 'Author', NULL, 1, 0)");
    }

    /** @return array{int,string,string} */
    private function runCommand(string $repositoryRoot, string $databasePath, string $staticHtmlRoot, bool $membersOnly = false): array
    {
        $command = 'FORUM_APPROVED_MEMBERS_ONLY=' . ($membersOnly ? 'true' : 'false')
            . ' ./v3 offline publish --repository-root=' . escapeshellarg($repositoryRoot)
            . ' --database-path=' . escapeshellarg($databasePath)
            . ' --static-html-root=' . escapeshellarg($staticHtmlRoot);
        $descriptor = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptor, $pipes, dirname(__DIR__));
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to run command.');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), (string) $stdout, (string) $stderr];
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $path . '/' . $entry;
            is_dir($entryPath) ? $this->removeTree($entryPath) : @unlink($entryPath);
        }
        @rmdir($path);
    }
}
