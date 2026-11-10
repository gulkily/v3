<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/**
 * Parsed fields mirror the record's headers one for one, including ones no reader uses yet; they are kept on purpose so the parser stays a complete view of the canonical format.
 */
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
        public readonly ?string $sourcePostId = null,
        public readonly ?string $sourcePostSha256 = null,
    ) {
    }
}
