<?php

declare(strict_types=1);

namespace ForumRewrite\Llm;

final class CompletionLimitException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Reply completion limit exhausted. Increase AGENT_REPLY_MAX_COMPLETION_TOKENS within its allowed range and explicitly retry.');
    }
}
