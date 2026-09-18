<?php

declare(strict_types=1);

namespace ForumRewrite\Invitation;

final class InvitationToken
{
    /**
     * New tokens are 16 random bytes encoded as 32 lowercase hexadecimal
     * characters. Earlier base64url and 256-bit hexadecimal tokens remain
     * redeemable.
     */
    public static function isValidBearer(string $token): bool
    {
        return preg_match('/^(?:[a-f0-9]{32}|[a-f0-9]{64}|[A-Za-z0-9_-]{21}[AQgw])$/D', $token) === 1;
    }
}
