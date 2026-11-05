<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class PostRecord
{
    public const FORMAT_MARKDOWN = 'markdown';
    public const FORMAT_PLAIN = 'plain';
    public const FORMATS = [self::FORMAT_MARKDOWN, self::FORMAT_PLAIN];

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
        public readonly ?int $importedScoreSeed = null,
        public readonly ?int $importedVoteCountSeed = null,
        public readonly ?string $eventDate = null,
        public readonly ?string $eventLocation = null,
        public readonly ?string $eventLink = null,
        public readonly ?string $eventTime = null,
        public readonly ?array $agentResponse = null,
        /** `markdown`, `plain`, or null when the record predates Body-Format (renders as plain). */
        public readonly ?string $bodyFormat = null,
        /** Inclusive last day of a multi-day event; null means the event is one day. */
        public readonly ?string $eventEndDate = null,
    ) {
    }

    public function rendersMarkdown(): bool
    {
        return $this->bodyFormat === self::FORMAT_MARKDOWN;
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
