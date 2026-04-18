<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class ThreadLabelRecord
{
    /**
     * @param string[] $labels
     */
    public function __construct(
        public readonly string $recordId,
        public readonly string $createdAt,
        public readonly string $threadId,
        public readonly string $operation,
        public readonly array $labels,
        public readonly ?string $authorIdentityId,
        public readonly ?string $reason,
        public readonly string $body,
    ) {
    }
}
