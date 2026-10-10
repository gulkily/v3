<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use Closure;

/** Line-oriented phase updates, throttled for terminals, pagers, and log files. */
final class ImportProgress
{
    private float $startedAt = 0;
    private float $lastReportedAt = 0;

    public function __construct(private readonly ?Closure $output)
    {
    }

    public function start(string $message): void
    {
        $this->startedAt = hrtime(true) / 1e9;
        $this->update($message, true);
    }

    public function update(string $message, bool $force = false): void
    {
        if ($this->output === null) { return; }
        $now = hrtime(true) / 1e9;
        if (!$force && $now - $this->lastReportedAt < 1) { return; }
        $this->lastReportedAt = $now;
        ($this->output)($message . sprintf(' (%.1fs elapsed)', $now - $this->startedAt));
    }
}
