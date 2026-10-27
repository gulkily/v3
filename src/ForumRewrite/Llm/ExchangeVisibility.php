<?php

declare(strict_types=1);

namespace ForumRewrite\Llm;

final class ExchangeVisibility
{
    public function __construct(private readonly string $repositoryRoot) {}

    public function allows(array $exchange): bool
    {
        return ($this->predicate())($exchange);
    }

    /** Only completed cleanup changes visibility; stale payloads may remain until maintenance. */
    public function predicate(): \Closure
    {
        return static fn (array $exchange): bool => ($exchange['context']['retraction_scrubbed'] ?? false) !== true;
    }
}
