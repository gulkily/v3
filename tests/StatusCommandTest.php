<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;

final class StatusCommandTest
{
    public function testRootUsageShowsStatusCommand(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(dirname(__DIR__), './v3');

        assertSame(1, $exitCode);
        assertStringContains('./v3 status', $stdout);
        assertSame('', $stderr);
    }

    public function testStatusReportsUnavailableStateWithoutInitializingRuntimeFiles(): void
    {
        $directory = $this->directory();
        $databasePath = $directory . '/missing-read-model.sqlite3';
        $queuePath = $directory . '/missing-queue.sqlite3';
        try {
            [$exitCode, $stdout, $stderr] = $this->statusCommand($databasePath, $queuePath);

            assertSame(0, $exitCode);
            assertStringContains('Read model: unavailable', $stdout);
            assertStringContains('Task queue status: not_initialized', $stdout);
            assertStringContains('Read-model rebuild task: absent', $stdout);
            assertStringContains('Run ./v3 task-queue enqueue-rebuild', $stdout);
            assertSame('', $stderr);
            assertSame(false, is_file($databasePath));
            assertSame(false, is_file($queuePath));
            assertSame(false, is_file($directory . '/forum-rewrite.lock'));
        } finally {
            $this->clean($directory);
        }
    }

    public function testStatusReportsQueuedAndRunningWorkerRebuilds(): void
    {
        $directory = $this->directory();
        $databasePath = $directory . '/read-model.sqlite3';
        $queuePath = $directory . '/queue.sqlite3';
        try {
            $repositoryRoot = __DIR__ . '/fixtures/parity_minimal_v1';
            (new ReadModelBuilder($repositoryRoot, $databasePath, new CanonicalRecordRepository($repositoryRoot)))->rebuild();
            $store = new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath));
            $store->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model');

            [$queuedCode, $queuedOutput, $queuedError] = $this->statusCommand($databasePath, $queuePath);
            assertSame(0, $queuedCode);
            assertStringContains('Read model: ready', $queuedOutput);
            assertStringContains('Read-model rebuild task: queued', $queuedOutput);
            assertStringContains('A rebuild is queued', $queuedOutput);
            assertSame('', $queuedError);

            $store->claimNext();
            [$runningCode, $runningOutput, $runningError] = $this->statusCommand($databasePath, $queuePath);
            assertSame(0, $runningCode);
            assertStringContains('Read-model rebuild task: running', $runningOutput);
            assertStringContains('A queued-worker rebuild is running', $runningOutput);
            assertSame('', $runningError);
        } finally {
            $this->clean($directory);
        }
    }

    public function testStatusHelpAndUnknownOptionsFollowCliContract(): void
    {
        [$helpCode, $helpOutput, $helpError] = $this->runCommand(dirname(__DIR__), './v3 status --help');
        [$errorCode, $errorOutput, $errorMessage] = $this->runCommand(dirname(__DIR__), './v3 status --unknown');

        assertSame(0, $helpCode);
        assertStringContains('./v3 status', $helpOutput);
        assertSame('', $helpError);
        assertSame(1, $errorCode);
        assertSame('', $errorOutput);
        assertStringContains('Error: Unknown option: --unknown', $errorMessage);
        assertStringContains('./v3 status', $errorMessage);
        assertStringNotContains('Stack trace:', $errorMessage);
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir() . '/forum-status-command-' . bin2hex(random_bytes(6));
        if (!mkdir($directory, 0700) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create status-command test directory.');
        }

        return $directory;
    }

    /** @return array{int,string,string} */
    private function statusCommand(string $databasePath, string $queuePath): array
    {
        return $this->runCommand(
            dirname(__DIR__),
            './v3 status --repository-root=' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1')
                . ' --database-path=' . escapeshellarg($databasePath)
                . ' --queue-database-path=' . escapeshellarg($queuePath),
        );
    }

    /** @return array{int,string,string} */
    private function runCommand(string $cwd, string $command): array
    {
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptor, $pipes, $cwd);
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

    private function clean(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($directory);
    }
}
