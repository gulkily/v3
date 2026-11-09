<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/** One operator's signed request that made another approved key an operator. */
final class OperatorGrantRecord
{
    public function __construct(
        public readonly string $recordId,
        public readonly string $createdAt,
        public readonly string $operatorIdentityId,
        public readonly string $targetIdentityId,
        public readonly string $targetUsername,
    ) {
    }
}
