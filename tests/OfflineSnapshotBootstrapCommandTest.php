<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

final class OfflineSnapshotBootstrapCommandTest
{
    public function testRebuildPublishesTheFirstPublicSnapshot(): void
    {
        [$root, $databasePath, $staticHtmlRoot] = $this->createPaths();
        try {
            [$exitCode, $output] = $this->runRebuild($databasePath, $staticHtmlRoot);

            assertSame(0, $exitCode);
            assertStringContains('Offline snapshot bootstrap: published at ', $output);
            foreach (['commit read model complete', 'validating candidate complete', 'Exclusive read-model lock acquired', 'Validating candidate before promotion complete', 'Replacing the live read model complete', 'Offline snapshot bootstrap complete'] as $message) {
                assertStringContains($message, $output);
            }
            assertSame(true, strpos($output, 'validating candidate complete') < strpos($output, '[3/3] Promoting'));
            assertSame(true, strpos($output, '[3/3] Read model promoted.') < strpos($output, 'Offline snapshot bootstrap...'));
            assertSame("SQLite format 3\000", file_get_contents($staticHtmlRoot . '/offline/snapshot.sqlite3', false, null, 0, 16));
        } finally {
            $this->removeTree($root);
        }
    }

    public function testRebuildSkipsPublicSnapshotInApprovedMembersOnlyMode(): void
    {
        [$root, $databasePath, $staticHtmlRoot] = $this->createPaths();
        try {
            [$exitCode, $output] = $this->runRebuild($databasePath, $staticHtmlRoot, true);

            assertSame(0, $exitCode);
            assertStringContains('Offline snapshot bootstrap: intentionally unavailable', $output);
            assertSame(false, is_file($staticHtmlRoot . '/offline/snapshot.sqlite3'));
        } finally {
            $this->removeTree($root);
        }
    }

    public function testRebuildReportsSnapshotReadinessFailureAfterPromotion(): void
    {
        [$root, $databasePath, $staticHtmlRoot] = $this->createPaths();
        try {
            file_put_contents($staticHtmlRoot, 'not a directory');
            [$exitCode, $output] = $this->runRebuild($databasePath, $staticHtmlRoot);

            assertSame(1, $exitCode);
            assertSame(true, is_file($databasePath));
            assertStringContains('Read-model rebuild failed while ensuring the initial offline snapshot', $output);
            assertStringContains('The read model was promoted, but offline snapshot readiness failed.', $output);
            assertStringContains('./v3 offline publish', $output);
            assertStringContains('Offline snapshot bootstrap failed (', $output);
            assertSame(false, str_contains($output, 'Offline snapshot bootstrap complete'));
        } finally {
            $this->removeTree($root);
        }
    }

    /** @return array{string,string,string} */
    private function createPaths(): array
    {
        $root = sys_get_temp_dir() . '/forum-offline-bootstrap-command-' . bin2hex(random_bytes(6));
        mkdir($root, 0700, true);

        return [$root, $root . '/read-model.sqlite3', $root . '/static_html'];
    }

    /** @return array{int,string} */
    private function runRebuild(string $databasePath, string $staticHtmlRoot, bool $membersOnly = false): array
    {
        $command = 'FORUM_STATIC_HTML_ROOT=' . escapeshellarg($staticHtmlRoot)
            . ' FORUM_APPROVED_MEMBERS_ONLY=' . ($membersOnly ? 'true' : 'false')
            . ' php ' . escapeshellarg(__DIR__ . '/../scripts/rebuild_read_model.php')
            . ' ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1')
            . ' ' . escapeshellarg($databasePath)
            . ' 2>&1';
        exec($command, $lines, $exitCode);

        return [$exitCode, implode("\n", $lines)];
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);

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
