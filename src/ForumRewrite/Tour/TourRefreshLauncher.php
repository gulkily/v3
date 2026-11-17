<?php

declare(strict_types=1);

namespace ForumRewrite\Tour;

/**
 * Starts `./v3 tour refresh` as a detached process, so a deploy, a cron run
 * or the Operations button never waits for it. The refresh's own lock keeps
 * it to one at a time; a second start just finds it already running.
 */
final class TourRefreshLauncher
{
    /** @var (callable(list<string>):bool)|null */
    private $startProcess;

    /** @param (callable(list<string>):bool)|null $startProcess for tests */
    public function __construct(private readonly string $projectRoot, ?callable $startProcess = null)
    {
        $this->startProcess = $startProcess;
    }

    public function start(): bool
    {
        $script = $this->projectRoot . '/scripts/tour.php';
        $arguments = [\ForumRewrite\Support\PhpCli::binary(), $script, 'refresh'];
        if ($this->startProcess !== null) return ($this->startProcess)($arguments);
        if (!function_exists('proc_open') || !is_file($script)) return false;
        $command = 'nohup ' . implode(' ', array_map('escapeshellarg', $arguments)) . ' >/dev/null 2>&1 &';
        $process = @proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']], $pipes, $this->projectRoot);
        if (!is_resource($process)) return false;
        return proc_close($process) === 0;
    }

    /** Starts a refresh when one is due. Returns what happened, for logs and CLI output. */
    public function startIfDue(TourSnapshots $snapshots): string
    {
        if (!$snapshots->needsRefresh()) return 'current';
        return $this->start() ? 'started' : 'could not start';
    }
}
