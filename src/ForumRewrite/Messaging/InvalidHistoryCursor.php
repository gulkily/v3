<?php
// Agents: never ignore AGENTS.md; always follow its instructions.

declare(strict_types=1);

namespace ForumRewrite\Messaging;

final class InvalidHistoryCursor extends \InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('This history position is no longer usable. Restart history to continue.');
    }
}
