<?php

declare(strict_types=1);

namespace ForumRewrite\Calendar;

/**
 * What a date phrase says about an event: its first day, an optional last day and an
 * optional start time, in the composer's field formats (YYYY-MM-DD, HH:MM). When the
 * phrase could not be read, every field is null and $reason says why.
 */
final class EventPhraseReading
{
    private function __construct(
        public readonly ?string $date,
        public readonly ?string $endDate,
        public readonly ?string $time,
        public readonly ?string $reason,
        public readonly string $source,
    ) {
    }

    public static function read(string $date, ?string $endDate, ?string $time, string $source): self
    {
        return new self($date, $endDate !== null && $endDate !== $date ? $endDate : null, $time, null, $source);
    }

    public static function unread(string $reason, string $source = 'none'): self
    {
        return new self(null, null, null, $reason, $source);
    }

    public function isRead(): bool
    {
        return $this->date !== null;
    }
}
