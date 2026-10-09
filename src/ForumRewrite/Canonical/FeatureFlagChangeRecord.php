<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class FeatureFlagChangeRecord
{
    public function __construct(
        public readonly string $recordId,
        public readonly string $createdAt,
        public readonly string $flagKey,
        public readonly bool $value,
        public readonly string $operatorIdentityId,
    ) {
    }
}
