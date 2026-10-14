<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class PostRetractionRecord
{
    public function __construct(
        public readonly string $recordId,
        public readonly string $createdAt,
        public readonly string $postId,
        public readonly string $operation,
        public readonly string $authorIdentityId,
    ) {
    }
}
