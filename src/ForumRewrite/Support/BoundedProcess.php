<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/** Linux process-group ownership prevents noisy or stalled children outliving their caller. */
final class BoundedProcess
{
    public static function available(): bool
    {
        return function_exists('proc_open') && function_exists('posix_kill') && is_executable('/usr/bin/setsid');
    }

    /** @return array{exit_code:int,stdout:string,stderr:string,bytes:int} */
    public static function run(array $command, OperationBudget $budget, int $maxOutput, string $stage, ?string $cwd = null): array
    {
        $budget->remaining($stage);
        if (!self::available()) throw new OperationLimitException($stage . ': bounded process support requires Linux setsid and PHP POSIX/proc_open.');
        $process = proc_open(['/usr/bin/setsid', '--', ...$command],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
        if (!is_resource($process)) throw new \RuntimeException($stage . ': unable to start child.');
        $pid = proc_get_status($process)['pid'];
        foreach ($pipes as $pipe) stream_set_blocking($pipe, false);
        $out = ['', '']; $bytes = 0;
        try {
            do {
                $budget->remaining($stage);
                foreach ([1, 2] as $fd) {
                    $chunk = fread($pipes[$fd], min(65536, max(1, $maxOutput - $bytes + 1)));
                    if ($chunk === false) throw new \RuntimeException($stage . ': child output read failed.');
                    $bytes += strlen($chunk);
                    if ($bytes > $maxOutput) throw new OperationLimitException($stage . ': output exceeds ' . $maxOutput . ' bytes.');
                    $out[$fd - 1] .= $chunk;
                }
                $status = proc_get_status($process);
                if (!$status['running'] && feof($pipes[1]) && feof($pipes[2])) break;
                if (!isset($exitCode) && !$status['running']) $exitCode = $status['exitcode'];
                usleep(10000);
            } while (true);
            return ['exit_code' => $exitCode ?? $status['exitcode'], 'stdout' => $out[0], 'stderr' => $out[1], 'bytes' => $bytes];
        } finally {
            // Kill the owned group even if its leader exited but left a child holding a pipe.
            @posix_kill(-$pid, 9);
            @proc_terminate($process, 9);
            foreach ($pipes as $pipe) fclose($pipe);
            proc_close($process);
        }
    }
}
