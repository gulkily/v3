<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class ThreadSubjectRecord
{
    public function __construct(
        public readonly string $recordId,
        public readonly string $createdAt,
        public readonly string $threadId,
        public readonly string $operation,
        public readonly string $subject,
        public readonly ?string $authorIdentityId,
        public readonly ?string $reason,
        public readonly string $body,
        public readonly ?string $actionAt = null,
        public readonly ?string $intentId = null,
    ) {
    }
}
