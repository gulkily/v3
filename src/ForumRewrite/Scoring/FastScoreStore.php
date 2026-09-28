<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

interface FastScoreStore
{
    /** @return array<string, mixed>|null */
    public function find(string $postId, string $contentHash, string $rubricRevision): ?array;

    /** @return array<string, mixed>|null */
    public function latestScoredForPostContent(string $postId, string $contentHash): ?array;

    /** @param array<string, mixed> $result @return array<string, mixed> */
    public function save(string $postId, string $contentHash, string $rubricRevision, array $result): array;
}
