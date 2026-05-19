<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class PostReactionRecord
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $recordId,
        public readonly string $createdAt,
        public readonly string $postId,
        public readonly string $operation,
        public readonly array $tags,
        public readonly ?string $authorIdentityId,
        public readonly ?string $reason,
        public readonly string $body,
    ) {
    }
}
