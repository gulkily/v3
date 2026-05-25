<?php

declare(strict_types=1);

namespace ForumRewrite\Analysis;

interface UnicodeRiskStore
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(string $postId, string $contentHash): ?array;

    /**
     * @param array<string, mixed> $deterministicFacts
     * @return array<string, mixed>
     */
    public function saveDeterministic(string $postId, string $contentHash, int $schemaVersion, array $deterministicFacts): array;

    /**
     * @param array<string, mixed> $llmReview
     * @return array<string, mixed>
     */
    public function saveLlmReview(string $postId, string $contentHash, array $llmReview): array;

    /**
     * @return array<string, mixed>
     */
    public function saveLlmFailure(string $postId, string $contentHash, string $failureMessage): array;
}
