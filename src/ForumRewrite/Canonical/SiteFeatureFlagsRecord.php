<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

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
