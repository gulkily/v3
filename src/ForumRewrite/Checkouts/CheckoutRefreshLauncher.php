<?php

declare(strict_types=1);

namespace ForumRewrite\Checkouts;

final class CheckoutRefreshLauncher
{
    public function launch(string $script, string $registry, string $state, string $token, ?string $path): bool
    {
        if (!function_exists('proc_open') || !function_exists('pcntl_async_signals')
            || !function_exists('posix_setsid') || !is_file($script)) {
            return false;
        }
        $args = [PHP_BINARY, $script, '__refresh', $token, '--registry=' . $registry, '--state-dir=' . $state];
        if ($path !== null) {
            $args[] = '--path=' . $path;
        }
        $command = 'nohup ' . implode(' ', array_map('escapeshellarg', $args)) . ' >/dev/null 2>&1 &';
        $process = @proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']], $pipes, dirname($script));
        if (!is_resource($process)) {
            return false;
        }
        return proc_close($process) === 0;
    }
}
