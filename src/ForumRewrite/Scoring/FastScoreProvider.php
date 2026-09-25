<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

interface FastScoreProvider
{
    /**
     * @param array<string, mixed> $context
     * @return array{status:string, probability:?float, source:string, signals:list<string>}
     */
    public function score(array $context): array;
}
