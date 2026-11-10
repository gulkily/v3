<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/**
 * One operator's signed request that made another approved key an operator.
 *
 * Parsed fields mirror the record's headers one for one, including ones no reader uses yet; they are kept on purpose so the parser stays a complete view of the canonical format.
 */
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
