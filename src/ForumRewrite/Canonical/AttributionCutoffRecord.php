<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class AttributionCutoffRecord
{
    public function __construct(
        public readonly string $recordId,
        public readonly string $createdAt,
        public readonly string $cutoffAt,
        public readonly string $operatorIdentityId,
    ) {
    }
}
