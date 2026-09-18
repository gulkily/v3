<?php

declare(strict_types=1);

namespace ForumRewrite\Write;

use RuntimeException;

/** Carries completed identity-bootstrap phases to the HTTP boundary on failure. */
final class IdentityBootstrapTimingException extends RuntimeException
{
    /** @param array<string, float|int> $timings */
    public function __construct(string $message, private readonly array $timings, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** @return array<string, float|int> */
    public function timings(): array
    {
        return $this->timings;
    }
}
