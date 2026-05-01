<?php

declare(strict_types=1);

namespace ForumRewrite\Analysis;

interface PostAnalysisStore
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(string $postId, string $contentHash): ?array;

    /**
     * @param array<string, mixed> $analysis
     */
    public function saveComplete(string $postId, string $contentHash, array $analysis): array;

    public function saveFailed(string $postId, string $contentHash, string $failureCode, string $failureMessage): array;
}
