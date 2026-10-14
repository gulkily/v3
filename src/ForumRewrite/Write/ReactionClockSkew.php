<?php

declare(strict_types=1);

namespace ForumRewrite\Write;

final class ReactionClockSkew extends \RuntimeException
{
    public function __construct(public readonly array $prepared)
    {
        parent::__construct('Reaction clock differs from server time; sign the prepared record.');
    }
}
