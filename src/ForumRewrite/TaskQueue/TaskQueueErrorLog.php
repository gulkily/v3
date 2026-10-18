<?php

declare(strict_types=1);

namespace ForumRewrite\TaskQueue;

use Throwable;

/** Append-only, size-rotated failure log for `task-queue run`, meant to be handed to an LLM for troubleshooting. */
final class TaskQueueErrorLog
{
    public function __construct(
        private readonly string $path,
        private readonly int $maxBytes = 1048576,
        private readonly int $keep = 5,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function write(string $event, string $message, array $context = []): void
    {
        try {
            $directory = dirname($this->path);
            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                return;
            }
            $this->rotate();
            $line = gmdate('c') . ' pid=' . getmypid() . ' event=' . $event . ' message=' . json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            foreach ($context as $key => $value) {
                $line .= ' ' . $key . '=' . json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            }
            file_put_contents($this->path, $line . "\n", FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            // Logging must never break the worker.
        }
    }

    /** @param array<string, mixed> $context */
    public function writeThrowable(string $event, Throwable $throwable, array $context = []): void
    {
        $this->write($event, $throwable->getMessage(), $context + [
            'class' => $throwable::class,
            'location' => $throwable->getFile() . ':' . $throwable->getLine(),
            'trace' => $throwable->getTraceAsString(),
        ]);
    }

    private function rotate(): void
    {
        clearstatcache(true, $this->path);
        if (!is_file($this->path) || filesize($this->path) < $this->maxBytes) {
            return;
        }
        $oldest = $this->path . '.' . $this->keep;
        if (is_file($oldest)) {
            unlink($oldest);
        }
        for ($i = $this->keep - 1; $i >= 1; $i--) {
            if (is_file($this->path . '.' . $i)) {
                rename($this->path . '.' . $i, $this->path . '.' . ($i + 1));
            }
        }
        rename($this->path, $this->path . '.1');
    }
}
