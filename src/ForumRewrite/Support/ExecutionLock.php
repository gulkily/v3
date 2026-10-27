<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use RuntimeException;

final class ExecutionLock
{
    private readonly int $timeoutSeconds;

    public function __construct(
        private readonly string $lockPath,
        ?int $timeoutSeconds = null,
    ) {
        $this->timeoutSeconds = $timeoutSeconds ?? self::defaultTimeoutSeconds();
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function withExclusiveLock(callable $callback): mixed
    {
        $locked = $this->withExclusiveLockTimed($callback);

        return $locked['result'];
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return array{result:T,timings:array{lock_wait:float,lock_hold:float}}
     */
    public function withExclusiveLockTimed(callable $callback): array
    {
        $directory = dirname($this->lockPath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create lock directory: ' . $directory);
        }

        $handle = fopen($this->lockPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open lock file: ' . $this->lockPath);
        }

        $start = hrtime(true);
        try {
            do {
                if (flock($handle, LOCK_EX | LOCK_NB)) {
                    $lockWait = $this->elapsedMilliseconds($start);
                    $holdStarted = hrtime(true);
                    try {
                        $result = $callback();
                        return [
                            'result' => $result,
                            'timings' => [
                                'lock_wait' => $lockWait,
                                'lock_hold' => $this->elapsedMilliseconds($holdStarted),
                            ],
                        ];
                    } finally {
                        flock($handle, LOCK_UN);
                    }
                }

                usleep(100000);
            } while (((hrtime(true) - $start) / 1000000000) < $this->timeoutSeconds);
        } finally {
            fclose($handle);
        }

        throw new RuntimeException('Timed out waiting for execution lock: ' . $this->lockPath);
    }

    private static function defaultTimeoutSeconds(): int
    {
        $raw = getenv('FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS');
        if ($raw === false || trim($raw) === '') {
            $raw = (string) (PrivateConfig::load(dirname(__DIR__, 3))['FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS'] ?? '');
        }
        if (trim($raw) === '') {
            return 5;
        }

        if (!ctype_digit(trim($raw))) {
            return 5;
        }

        return max(0, (int) trim($raw));
    }

    private function elapsedMilliseconds(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1000000, 1);
    }

    public function isLocked(): bool
    {
        $directory = dirname($this->lockPath);
        if (!is_dir($directory) || !is_file($this->lockPath)) {
            return false;
        }

        $handle = fopen($this->lockPath, 'r+');
        if ($handle === false) {
            return false;
        }

        try {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                flock($handle, LOCK_UN);
                return false;
            }

            return true;
        } finally {
            fclose($handle);
        }
    }
}
