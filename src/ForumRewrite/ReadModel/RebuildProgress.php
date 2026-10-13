<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

/** Shared timing boundaries for blocking rebuild lifecycle operations. */
final class RebuildProgress
{
    public function __construct(private readonly ?\Closure $reporter)
    {
    }

    public function measure(string $label, callable $operation): mixed
    {
        $startedAt = hrtime(true);
        $succeeded = false;
        $this->reporter?->__invoke($label . '...');
        try {
            $result = $operation();
            $succeeded = $result !== false;
            return $result;
        } finally {
            $this->reporter?->__invoke(sprintf(
                '%s %s (%.1f ms).',
                $label,
                $succeeded ? 'complete' : 'failed',
                (hrtime(true) - $startedAt) / 1000000,
            ));
        }
    }
}
