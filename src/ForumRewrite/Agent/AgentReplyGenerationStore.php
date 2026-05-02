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
     * @return array<string, mixed>
     */
    public function saveFailed(string $postId, string $contentHash, string $analysisHash, string $failureCode, string $failureMessage): array;

    /**
     * @return array<string, mixed>
     */
    public function markPosted(string $postId, string $contentHash, string $agentPostId, string $agentIdentityId, string $agentProfileSlug): array;
}
