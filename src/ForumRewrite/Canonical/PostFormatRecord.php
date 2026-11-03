<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/** A signed change of how one post's body displays; the post record itself never changes. */
final class PostFormatRecord
{
    public function __construct(
        public readonly string $recordId,
        public readonly string $createdAt,
        public readonly string $postId,
        public readonly string $bodyFormat,
        public readonly string $actorIdentityId,
    ) {
    }
}
