<?php

declare(strict_types=1);

namespace ForumRewrite\Write;

use RuntimeException;

/**
 * The server will never accept this compose attempt's post ID, so a retry with it would fail forever.
 * The compose page tells the browser to drop the ID; the next send gets a fresh one and keeps the text.
 */
final class ComposeAttemptRetired extends RuntimeException
{
    public function __construct(string $message, public readonly string $attempt)
    {
        parent::__construct($message);
    }
}
