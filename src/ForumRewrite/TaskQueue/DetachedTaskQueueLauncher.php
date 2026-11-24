<?php

declare(strict_types=1);

namespace ForumRewrite\TaskQueue;

final class DetachedTaskQueueLauncher
{
    /** @var (callable(string):bool)|null */
    private $startProcess;

    /**
     * @param (callable(string):bool)|null $startProcess
     */
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $queuePath,
        ?callable $startProcess = null,
    ) {
        $this->startProcess = $startProcess;
    }

    public function launchQueuedWorker(): string
    {
        if (getenv('FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED') !== 'true') {
            return 'disabled';
        }

        return $this->launch();
    }

    /**
     * Start the same fixed one-task worker for an operator action (Tools → Import) on a
     * site with no fresh worker. Not gated by FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED: the
     * caller must already have checked the root-operator session and the page's feature flag.
     */
    public function launchForOperator(): string
    {
        return $this->launch();
    }

    /**
     * Start a one-task worker for this queued task only (push notices on a host without
     * fastcgi_finish_request()). Not gated by FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED: the task's
     * deduplication key keeps it to one outstanding task, and the worker runs nothing else.
     */
    public function launchForTask(int $taskId): string
    {
        return $taskId > 0 ? $this->launch($taskId) : 'unavailable';
    }

    private function launch(?int $taskId = null): string
    {
        $workerScript = $this->projectRoot . '/scripts/task_queue.php';
        if (!is_file($workerScript) || !function_exists('proc_open')) {
            return 'unavailable';
        }

        // Under php-fpm, PHP_BINARY is not a command-line PHP; PhpCli picks the CLI binary.
        $command = 'nohup '
            . escapeshellarg(\ForumRewrite\Support\PhpCli::binary())
            . ' ' . escapeshellarg($workerScript)
            . ' run --quiet ' . ($taskId !== null ? '--task-id=' . $taskId : '--limit=1') . ' --queue-database-path=' . escapeshellarg($this->queuePath)
            . ' > /dev/null 2>&1 &';
        $startProcess = $this->startProcess ?? $this->startDetachedProcess(...);

        return $startProcess($command) ? 'launched' : 'failed';
    }

    private function startDetachedProcess(string $command): bool
    {
        $process = @proc_open(
            $command,
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', '/dev/null', 'a'],
                2 => ['file', '/dev/null', 'a'],
            ],
            $pipes,
            $this->projectRoot,
        );
        if (!is_resource($process)) {
            return false;
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        return proc_close($process) === 0;
    }
}
