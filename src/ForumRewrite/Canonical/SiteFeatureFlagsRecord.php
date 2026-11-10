<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/**
 * Parsed fields mirror the record's headers one for one, including ones no reader uses yet; they are kept on purpose so the parser stays a complete view of the canonical format.
 */
final class SiteFeatureFlagsRecord
{
    /**
     * @param array<string, bool> $values
     */
    public function __construct(
        public readonly array $values,
        public readonly ?string $updatedAt,
    ) {
    }

    public static function empty(): self
    {
        return new self([], null);
    }
}
