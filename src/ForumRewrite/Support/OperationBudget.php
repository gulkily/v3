<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

final class OperationBudget
{
    private readonly float $deadline;

    public function __construct(float $seconds)
    {
        if (!is_finite($seconds) || $seconds <= 0) throw new \InvalidArgumentException('Time limit must be positive and finite.');
        $this->deadline = hrtime(true) / 1e9 + $seconds;
    }

    public function remaining(string $stage): float
    {
        $remaining = $this->deadline - hrtime(true) / 1e9;
        if ($remaining <= 0) throw new OperationLimitException($stage . ': time limit exceeded.');
        return $remaining;
    }
}
