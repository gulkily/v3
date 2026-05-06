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
        $directory = dirname($this->lockPath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create lock directory: ' . $directory);
        }

        $handle = fopen($this->lockPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open lock file: ' . $this->lockPath);
        }

        $start = microtime(true);
        try {
            do {
                if (flock($handle, LOCK_EX | LOCK_NB)) {
                    try {
                        return $callback();
                    } finally {
                        flock($handle, LOCK_UN);
                    }
                }

                usleep(100000);
            } while ((microtime(true) - $start) < $this->timeoutSeconds);
        } finally {
            fclose($handle);
        }

        throw new RuntimeException('Timed out waiting for execution lock: ' . $this->lockPath);
    }

    private static function defaultTimeoutSeconds(): int
    {
        $raw = getenv('FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS');
        if ($raw === false || trim($raw) === '') {
            return 5;
        }

        if (!ctype_digit(trim($raw))) {
            return 5;
        }

        return max(0, (int) trim($raw));
    }

    public function isLocked(): bool
    {
        $directory = dirname($this->lockPath);
        if (!is_dir($directory)) {
            return false;
        }

        $handle = fopen($this->lockPath, 'c+');
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
