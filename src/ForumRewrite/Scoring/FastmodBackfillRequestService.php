<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

final class FastmodBackfillRequestService
{
    public function __construct(private readonly SqliteFastScoreStore $store)
    {
    }

    /**
     * @param array{counts:array<string,int>,candidates:list<array{post_id:string,content_hash:string}>} $audit
     * @return array{id:int,requested_count:int,queued_count:int,max_posts:int,max_cost_usd:float,estimated_cost_per_post_usd:float}
     */
    public function create(
        array $audit,
        string $rubricRevision,
        int $maxPosts,
        float $maxCostUsd,
        float $estimatedCostPerPostUsd,
    ): array {
        return $this->store->createBackfillBatch(
            $rubricRevision,
            $maxPosts,
            $maxCostUsd,
            $estimatedCostPerPostUsd,
            $audit['candidates'],
        );
    }
}
