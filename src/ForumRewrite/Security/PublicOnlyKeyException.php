<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

use RuntimeException;

/** An approved key without private access asked for messages. Responses carry reason=public_only. */
final class PublicOnlyKeyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This device can\'t read messages yet. Confirm it from another device of this account, or ask an operator.');
    }
}
