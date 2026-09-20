<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\TaskQueue\TaskQueueDatabaseConfig;

final class TaskQueueCommandTest
{
    public function testDefaultQueuePathIsPrivateProjectState(): void
    {
        assertSame(
            '/tmp/forum/state/private/internal_tasks.sqlite3',
            TaskQueueDatabaseConfig::path('/tmp/forum', '')
        );
    }

    public function testRootUsageShowsTaskQueueCommands(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(dirname(__DIR__), './v3');

        assertSame(1, $exitCode);
        assertStringContains('./v3 task-queue enqueue-rebuild', $stdout);
        assertStringContains('./v3 task-queue run', $stdout);
        assertStringContains('./v3 task-queue status', $stdout);
        assertStringContains('./v3 task-queue cron', $stdout);
        assertSame('', $stderr);
    }

    public function testTaskQueueCommandsEnqueueInspectAndDryRun(): void
    {
        $queuePath = sys_get_temp_dir() . '/forum-task-queue-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $progressQueuePath = sys_get_temp_dir() . '/forum-task-queue-progress-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            [$firstCode, $firstOutput, $firstError] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue enqueue-rebuild --queue-database-path=' . escapeshellarg($queuePath)
            );
            [$secondCode, $secondOutput, $secondError] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue enqueue-rebuild --queue-database-path=' . escapeshellarg($queuePath)
            );
            [$statusCode, $statusOutput, $statusError] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue status --queue-database-path=' . escapeshellarg($queuePath)
            );
            [$dryRunCode, $dryRunOutput, $dryRunError] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue run --dry-run --queue-database-path=' . escapeshellarg($queuePath)
            );
            $progressPdo = new PDO('sqlite:' . $progressQueuePath);
            new \ForumRewrite\TaskQueue\SqliteTaskQueueStore($progressPdo);
            $progressPdo->exec(
                "INSERT INTO internal_tasks (type, deduplication_key, status, attempts, max_attempts, requested_at)
                 VALUES ('unknown', 'progress-task', 'queued', 0, 1, '2026-01-01T00:00:00+00:00')"
            );
            [$runCode, $runOutput, $runError] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue run --queue-database-path=' . escapeshellarg($progressQueuePath)
            );
        } finally {
            @unlink($queuePath);
            @unlink($progressQueuePath);
        }

        assertSame(0, $firstCode);
        assertStringContains('Task enqueued: id=', $firstOutput);
        assertSame('', $firstError);
        assertSame(0, $secondCode);
        assertStringContains('Task already outstanding: id=', $secondOutput);
        assertSame('', $secondError);
        assertSame(0, $statusCode);
        assertStringContains('Queued: 1, running: 0, completed: 0, failed: 0', $statusOutput);
        assertSame('', $statusError);
        assertSame(0, $dryRunCode);
        assertStringContains('Task queue dry run', $dryRunOutput);
        assertStringContains('Queued tasks: 1', $dryRunOutput);
        assertSame('', $dryRunError);
        assertSame(0, $runCode);
        assertStringContains('Task queue worker starting', $runOutput);
        assertStringContains('Starting task id=', $runOutput);
        assertStringContains('Finished task id=', $runOutput);
        assertStringContains('Queue after:', $runOutput);
        assertStringContains('Elapsed:', $runOutput);
        assertSame('', $runError);
    }

    public function testTaskQueueCronReferenceUsesWorkerCommand(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(
            dirname(__DIR__),
            './v3 task-queue cron --log=/tmp/forum-task-queue-test.log'
        );

        assertSame(0, $exitCode);
        assertStringContains('Task queue cron reference', $stdout);
        assertStringContains('php scripts/task_queue.php run --quiet --limit=1', $stdout);
        assertStringContains('/tmp/forum-task-queue-test.log', $stdout);
        assertSame('', $stderr);
    }

    /**
     * @return array{0:int, 1:string, 2:string}
     */
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
}
