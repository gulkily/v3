<?php

declare(strict_types=1);

namespace ForumRewrite\TaskQueue;

/** Wake a single persisted scripture task; the queue remains the owner of execution. */
final class ScriptureTaskLauncher
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly string $artifactRoot,
        private readonly string $staticHtmlRoot,
        private readonly string $queuePath,
        private readonly mixed $startProcess = null,
    ) {}

    public function launch(int $taskId): string
    {
        if ($taskId < 1 || !is_file($this->projectRoot . '/scripts/task_queue.php') || !function_exists('proc_open')) {
            return 'unavailable';
        }
        $php = in_array(PHP_SAPI, ['cli', 'cli-server'], true) ? PHP_BINARY : 'php';
        $args = [$php, $this->projectRoot . '/scripts/task_queue.php', 'run', '--quiet',
            '--task-id=' . $taskId, '--lock-wait-seconds=60',
            '--repository-root=' . $this->repositoryRoot, '--database-path=' . $this->databasePath,
            '--static-html-root=' . $this->staticHtmlRoot, '--queue-database-path=' . $this->queuePath];
        $command = 'FORUM_PUBLIC_ARTIFACT_ROOT=' . escapeshellarg($this->artifactRoot)
            . ' nohup ' . implode(' ', array_map('escapeshellarg', $args)) . ' > /dev/null 2>&1 &';
        try {
            if ($this->startProcess !== null) {
                return ($this->startProcess)($command) ? 'launched' : 'failed';
            }
            $process = @proc_open($command, [0 => ['file', '/dev/null', 'r'],
                1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']], $pipes, $this->projectRoot);
            return is_resource($process) && proc_close($process) === 0 ? 'launched' : 'failed';
        } catch (\Throwable) {
            // Durable queued work remains available to the normal worker/explicit operator recovery.
            return 'failed';
        }
    }
}
