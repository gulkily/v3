<?php

declare(strict_types=1);

namespace ForumRewrite\UsageReports;

/** Outcome of one upload: stored, or refused with the reason the visitor sees. */
final class UsageReportResult
{
    public const STORED = 'stored';
    public const TOO_LARGE = 'too_large';
    public const RATE_LIMITED = 'rate_limited';
    public const STORE_FULL = 'store_full';

    private function __construct(
        public readonly string $status,
        public readonly int $id = 0,
        public readonly int $retryAfterSeconds = 0,
    ) {
    }

    public static function stored(int $id): self
    {
        return new self(self::STORED, $id);
    }

    public static function refused(string $status, int $retryAfterSeconds = 0): self
    {
        return new self($status, 0, $retryAfterSeconds);
    }

    public function isStored(): bool
    {
        return $this->status === self::STORED;
    }
}
