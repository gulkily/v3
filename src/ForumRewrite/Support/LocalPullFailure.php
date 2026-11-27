<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use RuntimeException;

/**
 * A messages-pull failure that carries extra fields for the page's Details disclosure.
 * Only public facts go in $details: names, fingerprints, counts. Never keys, cookies or message text.
 */
final class LocalPullFailure extends RuntimeException
{
    /** @param array<string, string|int|bool|null> $details */
    public function __construct(string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
