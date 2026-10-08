<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\Support\ExecutionLock;
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
            assertStringContains('Task queue executor: not_observed (last completed: none)', $stdout);
            assertStringContains('Automatic schema recovery: not_observed (detached launch: none)', $stdout);
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
            assertStringContains('Schema version: ' . ReadModelMetadata::SCHEMA_VERSION, $queuedOutput);
            assertStringContains('Schema fingerprint: ' . ReadModelMetadata::expectedSchemaIdentity()['schema_fingerprint'], $queuedOutput);
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

    public function testStatusReportsLockWaitingBeforeItsFinalReport(): void
    {
        $directory = $this->directory();
        $databasePath = $directory . '/read-model.sqlite3';
        $queuePath = $directory . '/queue.sqlite3';
        $lockPath = $directory . '/forum-rewrite.lock';
        $holder = null;
        $statusProcess = null;
        try {
            $repositoryRoot = __DIR__ . '/fixtures/parity_minimal_v1';
            (new ReadModelBuilder($repositoryRoot, $databasePath, new CanonicalRecordRepository($repositoryRoot)))->rebuild();
            $holder = $this->startExclusiveLockHolder($lockPath, $databasePath);
            $statusProcess = $this->startStatusProcess($databasePath, $queuePath);

            $initialOutput = $this->readUntil($statusProcess['stdout'], 'Waiting for shared lock before collecting status...', 2.0);
            assertStringContains('Waiting for shared lock before collecting status...', $initialOutput);
            assertSame(true, proc_get_status($statusProcess['process'])['running']);

            $this->releaseLockHolder($holder);
            $holder = null;
            [$exitCode, $remainingOutput, $stderr] = $this->finishStatusProcess($statusProcess);
            $statusProcess = null;

            assertSame(0, $exitCode);
            assertStringContains('v3 status', $initialOutput . $remainingOutput);
            assertStringContains('Read model: ready', $initialOutput . $remainingOutput);
            assertStringContains('Shared lock: unlocked', $initialOutput . $remainingOutput);
            assertSame('', $stderr);
        } finally {
            if (is_array($holder)) {
                $this->releaseLockHolder($holder);
            }
            if (is_array($statusProcess)) {
                $this->stopStatusProcess($statusProcess);
            }
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
            $this->statusCommandLine($databasePath, $queuePath),
        );
    }

    private function statusCommandLine(string $databasePath, string $queuePath): string
    {
        return './v3 status --repository-root=' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1')
                . ' --database-path=' . escapeshellarg($databasePath)
                . ' --queue-database-path=' . escapeshellarg($queuePath);
    }

    /** @return array{process:resource,stdin:resource,stdout:resource,stderr:resource} */
    private function startExclusiveLockHolder(string $lockPath, string $databasePath): array
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';
$lock = new \ForumRewrite\Support\ExecutionLock($argv[2]);
$lock->withExclusiveLock(static function () use ($argv): void {
    $pdo = new \PDO('sqlite:' . $argv[3]);
    $pdo->exec('BEGIN EXCLUSIVE');
    fwrite(STDOUT, "ready\n");
    fflush(STDOUT);
    fgets(STDIN);
    $pdo->exec('COMMIT');
});
PHP;
        $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script)
            . ' ' . escapeshellarg(dirname(__DIR__))
            . ' ' . escapeshellarg($lockPath)
            . ' ' . escapeshellarg($databasePath);
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start the lock holder.');
        }

        assertSame("ready\n", fgets($pipes[1]));

        return ['process' => $process, 'stdin' => $pipes[0], 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
    }

    /** @return array{process:resource,stdout:resource,stderr:resource} */
    private function startStatusProcess(string $databasePath, string $queuePath): array
    {
        $process = proc_open($this->statusCommandLine($databasePath, $queuePath), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start status command.');
        }
        fclose($pipes[0]);

        return ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
    }

    private function readUntil($stream, string $expected, float $timeoutSeconds): string
    {
        stream_set_blocking($stream, false);
        $output = '';
        $deadline = microtime(true) + $timeoutSeconds;
        do {
            $chunk = stream_get_contents($stream);
            if ($chunk !== false) {
                $output .= $chunk;
            }
            if (str_contains($output, $expected)) {
                stream_set_blocking($stream, true);
                return $output;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        stream_set_blocking($stream, true);
        return $output;
    }

    /** @param array{process:resource,stdin:resource,stdout:resource,stderr:resource} $holder */
    private function releaseLockHolder(array $holder): void
    {
        fwrite($holder['stdin'], "release\n");
        fclose($holder['stdin']);
        stream_get_contents($holder['stdout']);
        stream_get_contents($holder['stderr']);
        fclose($holder['stdout']);
        fclose($holder['stderr']);
        proc_close($holder['process']);
    }

    /** @param array{process:resource,stdout:resource,stderr:resource} $statusProcess */
    private function finishStatusProcess(array $statusProcess): array
    {
        $stdout = stream_get_contents($statusProcess['stdout']);
        $stderr = stream_get_contents($statusProcess['stderr']);
        fclose($statusProcess['stdout']);
        fclose($statusProcess['stderr']);

        return [proc_close($statusProcess['process']), (string) $stdout, (string) $stderr];
    }

    /** @param array{process:resource,stdout:resource,stderr:resource} $statusProcess */
    private function stopStatusProcess(array $statusProcess): void
    {
        proc_terminate($statusProcess['process']);
        fclose($statusProcess['stdout']);
        fclose($statusProcess['stderr']);
        proc_close($statusProcess['process']);
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
