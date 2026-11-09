<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class ApprovalSeedRecord
{
    public function __construct(
        public readonly string $approvedIdentityId,
        public readonly string $seedReason,
        public readonly string $body,
        /** Set when an operator grant wrote this seed; null for seeds from `./v3 approval seed`. */
        public readonly ?string $operatorGrantId = null,
    ) {
    }
}
