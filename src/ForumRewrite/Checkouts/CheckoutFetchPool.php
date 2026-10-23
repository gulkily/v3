<?php

declare(strict_types=1);

namespace ForumRewrite\Checkouts;

/** Fetch children never publish classification/history/cache state. */
final class CheckoutFetchPool
{
    public function fetch(array $tasks, array $environment, float $deadline): array
    {
        $pending = array_keys($tasks);
        $active = [];
        $results = [];
        try {
            while ($pending !== [] || $active !== []) {
                while (count($active) < 3 && $pending !== []) {
                    $key = array_shift($pending);
                    if (microtime(true) >= $deadline) {
                        $results[$key] = 124;
                        continue;
                    }
                    $task = $tasks[$key];
                    $process = proc_open(['git', '-C', $task['path'], ...$task['args']],
                        [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']],
                        $pipes, null, $environment);
                    if (!is_resource($process)) {
                        $results[$key] = 1;
                    } else {
                        $active[$key] = ['process' => $process, 'started' => microtime(true)];
                    }
                }
                foreach ($active as $key => $job) {
                    $status = proc_get_status($job['process']);
                    if ($status['running'] && (microtime(true) - $job['started'] >= 30 || microtime(true) >= $deadline)) {
                        proc_terminate($job['process'], 9);
                        $results[$key] = 124;
                    } elseif (!$status['running']) {
                        $results[$key] = $status['exitcode'];
                    } else {
                        continue;
                    }
                    proc_close($job['process']);
                    unset($active[$key]);
                }
                if ($active !== []) {
                    usleep(10000);
                }
            }
        } finally {
            foreach ($active as $job) {
                proc_terminate($job['process'], 9);
                proc_close($job['process']);
            }
        }
        return $results;
    }
}
