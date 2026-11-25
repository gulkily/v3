<?php

declare(strict_types=1);

namespace ForumRewrite\Push;

use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * P-256 keys in the raw forms web push uses, moved in and out of OpenSSL through DER.
 *
 * Only PHP 7.3-era OpenSSL functions are used (no `ec` parameters to `openssl_pkey_new`),
 * so the same code runs on every supported PHP version.
 */
final class P256Key
{
    /** SubjectPublicKeyInfo header for an uncompressed P-256 point. */
    private const SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public static function generate(): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key === false) {
            throw new RuntimeException('Unable to create a P-256 key: ' . (string) openssl_error_string());
        }

        return $key;
    }

    /** The 65-byte uncompressed public point of a key. */
    public static function rawPublicKey(OpenSSLAsymmetricKey $key): string
    {
        $details = openssl_pkey_get_details($key);
        if (!is_array($details) || !isset($details['ec']['x'], $details['ec']['y'])) {
            throw new RuntimeException('Not an EC key.');
        }

        return "\x04" . str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
    }

    public static function publicKeyFromRaw(string $raw): OpenSSLAsymmetricKey
    {
        self::checkRawPublicKey($raw);
        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode(hex2bin(self::SPKI_PREFIX) . $raw), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new InvalidArgumentException('The public key is not a point on P-256.');
        }

        return $key;
    }

    /** A private key from its 32-byte scalar and public point (SEC 1 ECPrivateKey). */
    public static function privateKeyFromRaw(string $privateScalar, string $rawPublic): OpenSSLAsymmetricKey
    {
        if (strlen($privateScalar) !== 32) {
            throw new InvalidArgumentException('A P-256 private key is 32 bytes.');
        }
        self::checkRawPublicKey($rawPublic);
        $der = hex2bin('30770201010420') . $privateScalar
            . hex2bin('a00a06082a8648ce3d030107a144034200') . $rawPublic;
        $pem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            throw new InvalidArgumentException('The private key does not match its public point.');
        }

        return $key;
    }

    /** A DER ECDSA signature as the 64-byte r || s form JWS uses. */
    public static function derSignatureToRaw(string $der): string
    {
        $offset = 2;
        if (strlen($der) < 8 || $der[0] !== "\x30") {
            throw new RuntimeException('Malformed ECDSA signature.');
        }
        $parts = [];
        for ($index = 0; $index < 2; $index++) {
            if ($der[$offset] !== "\x02") {
                throw new RuntimeException('Malformed ECDSA signature.');
            }
            $length = ord($der[$offset + 1]);
            $parts[] = str_pad(ltrim(substr($der, $offset + 2, $length), "\0"), 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $parts[0] . $parts[1];
    }

    /** The 64-byte r || s form as DER, for `openssl_verify`. */
    public static function rawSignatureToDer(string $raw): string
    {
        if (strlen($raw) !== 64) {
            throw new InvalidArgumentException('An ES256 signature is 64 bytes.');
        }
        $integer = static function (string $bytes): string {
            $bytes = ltrim($bytes, "\0");
            if ($bytes === '' || (ord($bytes[0]) & 0x80) !== 0) {
                $bytes = "\0" . $bytes;
            }

            return "\x02" . chr(strlen($bytes)) . $bytes;
        };
        $sequence = $integer(substr($raw, 0, 32)) . $integer(substr($raw, 32, 32));

        return "\x30" . chr(strlen($sequence)) . $sequence;
    }

    private static function checkRawPublicKey(string $raw): void
    {
        if (strlen($raw) !== 65 || $raw[0] !== "\x04") {
            throw new InvalidArgumentException('A P-256 public key is 65 bytes, starting with 0x04.');
        }
    }
}
