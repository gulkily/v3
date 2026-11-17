<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * Keeps the internal task queue moving while `./v3 start` runs, as cron does on a server.
 *
 * Starts `scripts/task_queue.php run --quiet --limit=1` whenever none is running and at
 * least the interval has passed since the last start. The queue's own lock makes a second
 * worker (cron, or one started by Tools → Import) exit at once. Each run is its own process
 * group, so Ctrl+C in the terminal doesn't reach it. A worker with nothing to do exits within
 * a second or two. When the server stops, finish() waits briefly and then leaves a busy worker
 * to complete its task on its own: stopping it would orphan an import (which runs in its own
 * session) and leave its task marked running until the queue's abandoned-task recovery.
 */
final class DevServerWorker
{
    /** @var resource|null */
    private $process = null;
    private float $lastStartedAt = 0.0;
    private int $starts = 0;

    /**
     * @param list<string> $extraArguments
     */
    public function __construct(
        private readonly string $projectRoot,
        private readonly float $intervalSeconds = 10.0,
        private readonly array $extraArguments = [],
    ) {
    }

    public static function available(): bool
    {
        return function_exists('proc_open') && is_executable('/usr/bin/setsid');
    }

    public function tick(?float $now = null): void
    {
        $now ??= microtime(true);
        if ($this->process !== null) {
            if (proc_get_status($this->process)['running']) {
                return;
            }
            proc_close($this->process);
            $this->process = null;
        }
        if ($this->starts > 0 && $now - $this->lastStartedAt < $this->intervalSeconds) {
            return;
        }
        $process = proc_open(
            ['/usr/bin/setsid', PHP_BINARY, $this->projectRoot . '/scripts/task_queue.php', 'run', '--quiet', '--limit=1', ...$this->extraArguments],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            $this->projectRoot,
        );
        $this->lastStartedAt = $now;
        $this->starts++;
        $this->process = is_resource($process) ? $process : null;
    }

    public function running(): bool
    {
        return $this->process !== null && proc_get_status($this->process)['running'];
    }

    public function starts(): int
    {
        return $this->starts;
    }

    /**
     * Wait up to the grace period for the current run; returns true when none is left running.
     * A run still busy after that keeps going detached.
     */
    public function finish(float $graceSeconds = 2.0): bool
    {
        if ($this->process === null) {
            return true;
        }
        $deadline = microtime(true) + $graceSeconds;
        while (proc_get_status($this->process)['running'] && microtime(true) < $deadline) {
            usleep(20000);
        }
        $done = !proc_get_status($this->process)['running'];
        if ($done) {
            proc_close($this->process);
        }
        $this->process = null;

        return $done;
    }
}
