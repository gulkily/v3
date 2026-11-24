<?php

declare(strict_types=1);

namespace ForumRewrite\Push;

use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Message encryption (RFC 8291, aes128gcm) and VAPID tokens (RFC 8292, ES256) for web push,
 * with PHP's bundled OpenSSL only. Tests check both against the RFC examples.
 */
final class WebPushCrypto
{
    public const RECORD_SIZE = 4096;
    /** Leaves room for the header, padding delimiter and tag inside one 4096-byte record. */
    public const MAX_PLAINTEXT_BYTES = 3000;

    /**
     * The request body for one push message to one browser.
     *
     * @param string $userPublicKey 65-byte `p256dh` key from the browser's subscription
     * @param string $authSecret 16-byte `auth` secret from the browser's subscription
     * @param string|null $salt test seam; 16 random bytes otherwise
     * @param OpenSSLAsymmetricKey|null $senderKey test seam; a fresh key otherwise
     */
    public static function encrypt(
        string $plaintext,
        string $userPublicKey,
        string $authSecret,
        ?string $salt = null,
        ?OpenSSLAsymmetricKey $senderKey = null,
    ): string {
        if (strlen($plaintext) > self::MAX_PLAINTEXT_BYTES) {
            throw new InvalidArgumentException('A push message holds at most ' . self::MAX_PLAINTEXT_BYTES . ' bytes.');
        }
        if (strlen($authSecret) !== 16) {
            throw new InvalidArgumentException('The auth secret is 16 bytes.');
        }
        $salt ??= random_bytes(16);
        if (strlen($salt) !== 16) {
            throw new InvalidArgumentException('The salt is 16 bytes.');
        }
        $senderKey ??= P256Key::generate();
        $senderPublic = P256Key::rawPublicKey($senderKey);

        $shared = openssl_pkey_derive(P256Key::publicKeyFromRaw($userPublicKey), $senderKey, 32);
        if ($shared === false) {
            throw new RuntimeException('Key agreement failed: ' . (string) openssl_error_string());
        }
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $userPublicKey . $senderPublic, $authSecret);
        $contentKey = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        // One record: the plaintext, then the 0x02 delimiter that marks the last record.
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext . "\x02", 'aes-128-gcm', $contentKey, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed: ' . (string) openssl_error_string());
        }

        return $salt . pack('N', self::RECORD_SIZE) . chr(strlen($senderPublic)) . $senderPublic . $ciphertext . $tag;
    }

    /** A signed VAPID JWT for one push service origin. */
    public static function vapidToken(string $audience, string $subject, int $expiresAt, OpenSSLAsymmetricKey $privateKey): string
    {
        $input = self::base64UrlEncode('{"typ":"JWT","alg":"ES256"}') . '.'
            . self::base64UrlEncode(json_encode(['aud' => $audience, 'exp' => $expiresAt, 'sub' => $subject], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $signature = '';
        if (!openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Signing failed: ' . (string) openssl_error_string());
        }

        return $input . '.' . self::base64UrlEncode(P256Key::derSignatureToRaw($signature));
    }

    /** True when a VAPID JWT carries a valid ES256 signature by the given public key. */
    public static function verifyVapidToken(string $token, string $rawPublicKey): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        $signature = self::base64UrlDecode($parts[2]);
        if ($signature === null || strlen($signature) !== 64) {
            return false;
        }

        return openssl_verify($parts[0] . '.' . $parts[1], P256Key::rawSignatureToDer($signature), P256Key::publicKeyFromRaw($rawPublicKey), OPENSSL_ALGO_SHA256) === 1;
    }

    /** The `Authorization` header value for a push request. */
    public static function vapidAuthorization(string $token, string $rawPublicKey): string
    {
        return 'vapid t=' . $token . ', k=' . self::base64UrlEncode($rawPublicKey);
    }

    public static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $text): ?string
    {
        if ($text === '' || preg_match('/^[A-Za-z0-9_-]+={0,2}$/', $text) !== 1) {
            return null;
        }
        $decoded = base64_decode(strtr(rtrim($text, '='), '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
