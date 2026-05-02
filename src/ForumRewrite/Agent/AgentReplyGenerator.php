<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

interface AgentReplyGenerator
{
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function generate(array $context): array;
}
