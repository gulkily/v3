<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Push\P256Key;
use ForumRewrite\Push\WebPushCrypto;

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Failed asserting that values are identical. Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

/** The web push sender's crypto, checked against the RFC 8291 and RFC 8292 examples. */
final class WebPushCryptoTest
{
    public function testEncryptMatchesRfc8291AppendixA(): void
    {
        $senderPrivate = $this->decode('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw');
        $senderPublic = $this->decode('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8');

        $body = WebPushCrypto::encrypt(
            'When I grow up, I want to be a watermelon',
            $this->decode('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4'),
            $this->decode('BTBZMqHH6r4Tts7J_aSIgg'),
            $this->decode('DGv6ra1nlYgDCS1FRnbzlw'),
            P256Key::privateKeyFromRaw($senderPrivate, $senderPublic),
        );

        assertSame(
            'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN',
            WebPushCrypto::base64UrlEncode($body),
        );
    }

    public function testTheBrowserSideCanDecryptAFreshMessage(): void
    {
        $browserKey = P256Key::generate();
        $browserPublic = P256Key::rawPublicKey($browserKey);
        $auth = random_bytes(16);
        $body = WebPushCrypto::encrypt('{"title":"ZenMemes"}', $browserPublic, $auth);

        assertSame('{"title":"ZenMemes"}', self::decryptAsBrowser($body, $browserKey, $auth));
    }

    public function testRfc8292ExampleTokenVerifiesWithItsKey(): void
    {
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJFUzI1NiJ9.eyJhdWQiOiJodHRwczovL3B1c2guZXhhbXBsZS5uZXQiLCJleHAiOjE0NTM1MjM3NjgsInN1YiI6Im1haWx0bzpwdXNoQGV4YW1wbGUuY29tIn0.i3CYb7t4xfxCDquptFOepC9GAu_HLGkMlMuCGSK2rpiUfnK9ojFwDXb1JrErtmysazNjjvW2L9OkSSHzvoD1oA';
        $key = $this->decode('BA1Hxzyi1RUM1b5wjxsn7nGxAszw2u61m164i3MrAIxHF6YK5h4SDYic-dRuU_RCPCfA5aq9ojSwk5Y2EmClBPs');

        assertSame(true, WebPushCrypto::verifyVapidToken($token, $key));
        assertSame(false, WebPushCrypto::verifyVapidToken(substr($token, 0, -2) . 'AA', $key));
    }

    public function testAFreshTokenNamesTheAudienceAndVerifies(): void
    {
        $key = P256Key::generate();
        $token = WebPushCrypto::vapidToken('https://web.push.apple.com', 'https://zenmemes.com', 1_800_000_000, $key);
        [$header, $claims] = explode('.', $token);

        assertSame('{"typ":"JWT","alg":"ES256"}', WebPushCrypto::base64UrlDecode($header));
        assertSame('{"aud":"https://web.push.apple.com","exp":1800000000,"sub":"https://zenmemes.com"}', WebPushCrypto::base64UrlDecode($claims));
        assertSame(true, WebPushCrypto::verifyVapidToken($token, P256Key::rawPublicKey($key)));
        assertSame(
            'vapid t=' . $token . ', k=' . WebPushCrypto::base64UrlEncode(P256Key::rawPublicKey($key)),
            WebPushCrypto::vapidAuthorization($token, P256Key::rawPublicKey($key)),
        );
    }

    public function testBadInputsAreRefused(): void
    {
        $public = P256Key::rawPublicKey(P256Key::generate());
        foreach ([
            static fn () => WebPushCrypto::encrypt('x', substr($public, 1), random_bytes(16)),
            static fn () => WebPushCrypto::encrypt('x', $public, random_bytes(15)),
            static fn () => WebPushCrypto::encrypt(str_repeat('x', WebPushCrypto::MAX_PLAINTEXT_BYTES + 1), $public, random_bytes(16)),
            static fn () => WebPushCrypto::encrypt('x', "\x04" . str_repeat("\x01", 64), random_bytes(16)),
        ] as $index => $call) {
            $refused = false;
            try {
                $call();
            } catch (InvalidArgumentException) {
                $refused = true;
            }
            assertSame([$index, true], [$index, $refused]);
        }
        assertSame(null, WebPushCrypto::base64UrlDecode('not base64!'));
    }

    /** What a browser does with a push body (RFC 8291 section 3.4), used to check fresh messages. */
    public static function decryptAsBrowser(string $body, OpenSSLAsymmetricKey $browserKey, string $auth): string
    {
        $salt = substr($body, 0, 16);
        $idLength = ord($body[20]);
        $senderPublic = substr($body, 21, $idLength);
        $record = substr($body, 21 + $idLength);
        $browserPublic = P256Key::rawPublicKey($browserKey);
        $shared = openssl_pkey_derive(P256Key::publicKeyFromRaw($senderPublic), $browserKey, 32);
        $ikm = hash_hkdf('sha256', (string) $shared, 32, "WebPush: info\0" . $browserPublic . $senderPublic, $auth);
        $plain = openssl_decrypt(
            substr($record, 0, -16),
            'aes-128-gcm',
            hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt),
            OPENSSL_RAW_DATA,
            hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt),
            substr($record, -16),
        );
        if (!is_string($plain) || !str_ends_with($plain, "\x02")) {
            throw new RuntimeException('The push body did not decrypt.');
        }

        return substr($plain, 0, -1);
    }

    private function decode(string $text): string
    {
        return (string) WebPushCrypto::base64UrlDecode($text);
    }
}
