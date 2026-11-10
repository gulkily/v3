<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/**
 * Parsed fields mirror the record's headers one for one, including ones no reader uses yet; they are kept on purpose so the parser stays a complete view of the canonical format.
 */
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
        public readonly ?string $actionAt = null,
        public readonly ?string $intentId = null,
    ) {
    }
}
