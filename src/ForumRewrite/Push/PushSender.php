<?php

declare(strict_types=1);

namespace ForumRewrite\Push;

use Closure;
use JsonException;

/**
 * Sends one encrypted message to one browser and reports the push service's answer.
 *
 * The transport is `fn (string $url, array $headers, string $body): array{0:int,1:string}`
 * returning the HTTP status (0 when the request failed) and body; the default sends over HTTPS.
 */
final class PushSender
{
    public const TOKEN_LIFETIME_SECONDS = 12 * 3600;
    public const TIME_TO_LIVE_SECONDS = 24 * 3600;

    private readonly Closure $transport;

    public function __construct(
        private readonly PushVapidKeys $keys,
        private readonly string $subject,
        ?callable $transport = null,
        private readonly PushEndpointPolicy $policy = new PushEndpointPolicy(),
        private readonly ?Closure $clock = null,
    ) {
        $this->transport = $transport !== null ? Closure::fromCallable($transport) : Closure::fromCallable([self::class, 'streamTransport']);
    }

    /** @param array<string, mixed> $payload shown by the service worker (title, body, url, tag) */
    public function send(PushSubscription $subscription, array $payload, string $urgency = 'normal'): PushSendResult
    {
        if (!$this->policy->allows($subscription->endpoint)) {
            return PushSendResult::refused('unknown push service');
        }
        try {
            $plaintext = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return PushSendResult::refused('payload is not text');
        }
        if (strlen($plaintext) > WebPushCrypto::MAX_PLAINTEXT_BYTES) {
            return PushSendResult::refused('payload too large');
        }
        $now = $this->clock !== null ? ($this->clock)() : time();
        $token = WebPushCrypto::vapidToken(
            PushEndpointPolicy::audience($subscription->endpoint),
            $this->subject,
            $now + self::TOKEN_LIFETIME_SECONDS,
            $this->keys->privateKey(),
        );
        $body = WebPushCrypto::encrypt($plaintext, $subscription->userPublicKey, $subscription->authSecret);
        [$status, $responseBody] = ($this->transport)($subscription->endpoint, [
            'Authorization' => WebPushCrypto::vapidAuthorization($token, $this->keys->publicKeyRaw()),
            'Content-Encoding' => 'aes128gcm',
            'Content-Type' => 'application/octet-stream',
            'TTL' => (string) self::TIME_TO_LIVE_SECONDS,
            'Urgency' => in_array($urgency, ['very-low', 'low', 'normal', 'high'], true) ? $urgency : 'normal',
        ], $body);

        return $status === 0 ? PushSendResult::refused($responseBody !== '' ? $responseBody : 'no answer') : PushSendResult::fromResponse($status, $responseBody);
    }

    /**
     * POST with PHP streams, as `PublicKeyPublishDestination` does: TLS verified, no redirects.
     *
     * @param array<string, string> $headers
     * @return array{0:int,1:string}
     */
    public static function streamTransport(string $url, array $headers, string $body, float $timeout = 10.0): array
    {
        $lines = 'Content-Length: ' . strlen($body) . "\r\nConnection: close\r\n";
        foreach ($headers as $name => $value) {
            $lines .= $name . ': ' . $value . "\r\n";
        }
        $context = stream_context_create([
            'http' => ['method' => 'POST', 'header' => $lines, 'content' => $body, 'timeout' => $timeout,
                'ignore_errors' => true, 'follow_location' => 0, 'protocol_version' => 1.1],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
        ]);
        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) {
            return [0, 'connection failed'];
        }
        try {
            $status = 0;
            foreach (stream_get_meta_data($stream)['wrapper_data'] ?? [] as $header) {
                if (is_string($header) && preg_match('#^HTTP/\S+ (\d{3})#', $header, $match) === 1) {
                    $status = (int) $match[1];
                }
            }

            return [$status, (string) stream_get_contents($stream, 4096)];
        } finally {
            fclose($stream);
        }
    }
}
