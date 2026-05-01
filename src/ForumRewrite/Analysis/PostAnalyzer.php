<?php

declare(strict_types=1);

namespace ForumRewrite\Analysis;

interface PostAnalyzer
{
    /**
     * @param array<string, mixed> $context
     * @return array{
     *   provider:string,
     *   provider_model:string,
     *   provider_request_id:?string,
     *   moderation:array<string, mixed>,
     *   engagement:array<string, mixed>,
     *   quality:array<string, mixed>,
     *   raw_response:array<string, mixed>
     * }
     */
    public function analyze(array $context): array;
}
