<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * Status lines for operator commands: one stderr line per step, stamped with
 * time since the command started, in the same shape as purge-history's output.
 */
final class CommandProgress
{
    private readonly int $startedAt;

    /** @param resource|null $stream */
    public function __construct(
        private readonly bool $enabled,
        private $stream = null,
    ) {
        $this->startedAt = hrtime(true);
        $this->stream ??= STDERR;
    }

    public function step(string $message): void
    {
        if ($this->enabled) {
            $this->line($message);
        }
    }

    /** Printed even when step lines are off (quiet mode). */
    public function finish(string $message): void
    {
        fwrite($this->stream, sprintf("%s in %.2fs\n", $message, $this->elapsed()));
    }

    public function elapsed(): float
    {
        return (hrtime(true) - $this->startedAt) / 1e9;
    }

    /** @return \Closure(string):void|null */
    public function reporter(): ?\Closure
    {
        return $this->enabled ? fn (string $message) => $this->step($message) : null;
    }

    private function line(string $message): void
    {
        fwrite($this->stream, sprintf("[%6.2fs] %s\n", $this->elapsed(), $message));
    }
}
