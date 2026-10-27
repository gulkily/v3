<?php

declare(strict_types=1);
namespace ForumRewrite\Support;

/** Supervises the CLI worker outside blocking PHP/native/Git calls. Linux/WSL only. */
final class InvocationSupervisor
{
    private static $channel = null;

    public static function stage(string $stage): void
    {
        if (is_resource(self::$channel)) @fwrite(self::$channel, substr(str_replace(["\n", "\r"], ' ', $stage), 0, 2048) . "\n");
    }

    public static function run(callable $work, float $seconds, ?string $repository = null): int
    {
        $budget = new OperationBudget($seconds);
        foreach (['pcntl_fork', 'pcntl_waitpid', 'posix_setsid', 'posix_kill', 'pcntl_async_signals'] as $function) {
            if (!function_exists($function)) throw new OperationLimitException('Import supervision requires Linux/WSL PHP PCNTL and POSIX; missing ' . $function . '.');
        }
        if (!is_dir('/proc/self')) throw new OperationLimitException('Import supervision requires Linux /proc.');
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        if ($pair === false) throw new OperationLimitException('Unable to create import supervision channel.');
        foreach ($pair as $channel) stream_set_blocking($channel, false);
        $pid = pcntl_fork();
        if ($pid === -1) throw new OperationLimitException('Unable to create supervised import worker.');
        if ($pid === 0) {
            fclose($pair[0]); self::$channel = $pair[1];
            if (posix_setsid() === -1) exit(1);
            try { exit((int) $work()); }
            catch (\Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
        }
        fclose($pair[1]);
        $oldAsync = pcntl_async_signals(true);
        $oldInt = pcntl_signal_get_handler(SIGINT); $oldTerm = pcntl_signal_get_handler(SIGTERM);
        $interrupted = false;
        pcntl_signal(SIGINT, static function () use (&$interrupted): void { $interrupted = true; });
        pcntl_signal(SIGTERM, static function () use (&$interrupted): void { $interrupted = true; });
        $known = []; $lastStage = 'startup'; $buffer = ''; $reaped = false;
        try {
            while (true) {
                self::discover($pid, $known);
                $chunk = stream_get_contents($pair[0], 65536);
                if ($chunk !== false) $buffer .= $chunk;
                while (($end = strpos($buffer, "\n")) !== false) {
                    $lastStage = substr($buffer, 0, $end); $buffer = substr($buffer, $end + 1);
                }
                $waited = pcntl_waitpid($pid, $status, WNOHANG);
                if ($waited === $pid) {
                    $reaped = true;
                    if (pcntl_wifexited($status)) return pcntl_wexitstatus($status);
                    throw new OperationLimitException('Import worker terminated during ' . $lastStage . '.');
                }
                if ($interrupted) throw new OperationLimitException('Import interrupted during ' . $lastStage . '.');
                $budget->remaining('Import (' . $seconds . ' seconds), stage ' . $lastStage);
                usleep(20000);
            }
        } catch (OperationLimitException $error) {
            fwrite(STDERR, 'Import failed: ' . preg_replace('/[\x00-\x1f\x7f]/', '?', $error->getMessage()) . "\n");
            if ($repository !== null && is_file($repository . '/.git/instance-import/pending.json')) {
                fwrite(STDERR, "An interrupted merge is pending. Use --resume with the same destination options.\n");
            } else {
                fwrite(STDERR, "No prepared merge is pending. Retry with a corrected/smaller source or an explicit finite --time-limit.\n");
            }
            return 1;
        } finally {
            // Stop spawning before collecting the final descendants, including
            // nested setsid groups created by bounded signature verification.
            if (!$reaped) @posix_kill($pid, SIGSTOP);
            self::discover($pid, $known);
            foreach (array_reverse($known, true) as $child => $birth) {
                $stat = self::stat($child);
                if ($stat !== null && $stat['birth'] === $birth) {
                    if ($stat['group'] === $child) @posix_kill(-$child, SIGKILL);
                    @posix_kill($child, SIGKILL);
                }
            }
            @posix_kill(-$pid, SIGKILL);
            if (!$reaped) { @posix_kill($pid, SIGKILL); pcntl_waitpid($pid, $status); }
            fclose($pair[0]);
            pcntl_signal(SIGINT, $oldInt); pcntl_signal(SIGTERM, $oldTerm); pcntl_async_signals($oldAsync);
        }
    }

    private static function stat(int $pid): ?array
    {
        $raw = @file_get_contents('/proc/' . $pid . '/stat');
        if ($raw === false) return null;
        $fields = explode(' ', substr($raw, strrpos($raw, ')') + 2));
        return ['parent' => (int) $fields[1], 'group' => (int) $fields[2], 'birth' => $fields[19]];
    }

    private static function discover(int $root, array &$known): void
    {
        $children = []; $stats = [];
        foreach (glob('/proc/[0-9]*/stat') ?: [] as $file) {
            $pid = (int) basename(dirname($file)); $stat = self::stat($pid);
            if ($stat === null) continue;
            $stats[$pid] = $stat; $children[$stat['parent']][] = $pid;
        }
        $queue = [$root];
        for ($i = 0; $i < count($queue); $i++) {
            $pid = $queue[$i];
            if (isset($stats[$pid])) $known[$pid] = $stats[$pid]['birth'];
            foreach ($children[$pid] ?? [] as $child) $queue[] = $child;
        }
    }
}
