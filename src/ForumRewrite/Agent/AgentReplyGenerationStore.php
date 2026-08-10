<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

interface AgentReplyGenerationStore
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByTarget(string $postId, string $contentHash): ?array;

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $generation
     * @return array<string, mixed>
     */
    public function saveComplete(array $context, array $generation): array;

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function reserveGeneration(array $context): array;

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $requestContext
     * @return array<string, mixed>
     */
    public function requestForTarget(array $context, array $requestContext): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function claimNextRequested(int $limit = 1): array;

    /**
     * @return array<string, mixed>|null
     */
    public function claimRequestedForPost(string $postId): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function reservePosting(string $postId, string $contentHash): ?array;

    /**
     * @return array<string, mixed>
     */
    public function saveFailed(string $postId, string $contentHash, string $analysisHash, string $failureCode, string $failureMessage): array;

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    public function markSkipped(string $postId, string $contentHash, string $reason, array $details = []): array;

    /**
     * @return array<string, mixed>
     */
    public function markPosted(string $postId, string $contentHash, string $agentPostId, string $agentIdentityId, string $agentProfileSlug): array;
}
