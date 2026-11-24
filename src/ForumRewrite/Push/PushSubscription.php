<?php

declare(strict_types=1);

namespace ForumRewrite\Push;

use InvalidArgumentException;

/** One browser's push address and the two keys it gave for encrypting to it. */
final class PushSubscription
{
    public function __construct(
        public readonly string $endpoint,
        public readonly string $userPublicKey,
        public readonly string $authSecret,
    ) {
        if (strlen($userPublicKey) !== 65 || $userPublicKey[0] !== "\x04") {
            throw new InvalidArgumentException('The p256dh key must be a 65-byte P-256 point.');
        }
        if (strlen($authSecret) !== 16) {
            throw new InvalidArgumentException('The auth secret must be 16 bytes.');
        }
    }

    /** From the browser's `PushSubscription.toJSON()` fields (base64url keys). */
    public static function fromBrowser(string $endpoint, string $p256dh, string $auth): self
    {
        return new self(
            $endpoint,
            (string) WebPushCrypto::base64UrlDecode($p256dh),
            (string) WebPushCrypto::base64UrlDecode($auth),
        );
    }
}
