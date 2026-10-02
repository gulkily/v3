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

        $workerScript = $this->projectRoot . '/scripts/task_queue.php';
        if (!is_file($workerScript) || !function_exists('proc_open')) {
            return 'unavailable';
        }

        $command = 'nohup '
            . escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg($workerScript)
            . ' run --quiet --limit=1 --queue-database-path=' . escapeshellarg($this->queuePath)
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
