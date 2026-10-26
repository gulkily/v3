<?php

declare(strict_types=1);
namespace ForumRewrite\Images;

use RuntimeException;

/** One deadline shared by all lock waits in an export request; never bounds build CPU time. */
final class ArchiveLocks
{
    private readonly int $deadline;

    public function __construct(private readonly string $directory, float $waitSeconds = 5.0)
    {
        $this->deadline = hrtime(true) + (int) ($waitSeconds * 1_000_000_000);
    }

    public function withExclusiveLock(string $name, callable $callback): mixed
    {
        $handle = fopen($this->directory . '/' . $name, 'c+');
        if ($handle === false) throw new RuntimeException('Cannot open archive coordination file.');
        try {
            do {
                if (flock($handle, LOCK_EX | LOCK_NB)) {
                    try { return $callback(); }
                    finally { flock($handle, LOCK_UN); }
                }
                $remaining = $this->deadline - hrtime(true);
                if ($remaining <= 0) throw new ArchiveBusyException('Another backup is being prepared. Retry the download shortly.');
                usleep((int) min(50_000, max(1, $remaining / 1000)));
            } while (true);
        } finally {
            fclose($handle);
        }
    }
}
