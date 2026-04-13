<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class PostRecord
{
    /**
     * @param string[] $boardTags
     * @param string[] $taskDependsOn
     * @param string[] $taskSources
     */
    public function __construct(
        public readonly string $postId,
        public readonly string $createdAt,
        public readonly array $boardTags,
        public readonly ?string $threadId,
        public readonly ?string $parentId,
        public readonly ?string $authorIdentityId,
        public readonly ?string $subject,
        public readonly ?string $threadType,
        public readonly ?string $taskStatus,
        public readonly ?float $taskPresentabilityImpact,
        public readonly ?float $taskImplementationDifficulty,
        public readonly array $taskDependsOn,
        public readonly array $taskSources,
        public readonly string $body,
    ) {
    }

    public function isReply(): bool
    {
        return $this->threadId !== null;
    }

    public function isRoot(): bool
    {
        return $this->threadId === null && $this->parentId === null;
    }
}
