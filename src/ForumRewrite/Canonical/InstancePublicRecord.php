<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class InstancePublicRecord
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly array $headers,
        public readonly string $body,
    ) {
    }
}
