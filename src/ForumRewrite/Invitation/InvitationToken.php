<?php

declare(strict_types=1);

namespace ForumRewrite\Invitation;

final class InvitationToken
{
    /**
     * A new token is 16 random bytes encoded as unpadded base64url. The final
     * character is restricted to canonical encodings of a 128-bit value.
     * Legacy 256-bit hexadecimal tokens remain redeemable.
     */
    public static function isValidBearer(string $token): bool
    {
        return preg_match('/^(?:[a-f0-9]{64}|[A-Za-z0-9_-]{21}[AQgw])$/D', $token) === 1;
    }
}
