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

    /** A current snapshot for one served query; never cached across requests. */
    public function predicate(): \Closure
    {
        $retracted = ExchangeRetractionState::read($this->repositoryRoot);
        return static function (array $exchange) use ($retracted): bool {
            if ($retracted === null || ($exchange['context']['retraction_scrubbed'] ?? false) === true) return false;
            $ids = ExchangeDependencies::classify($exchange);
            if ($ids === null) return false;
            foreach ($ids as $id) if (isset($retracted[$id])) return false;
            return true;
        };
    }
}
