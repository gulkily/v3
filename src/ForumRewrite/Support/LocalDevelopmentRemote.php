<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use ForumRewrite\Import\InstanceArchiveDownloader;
use RuntimeException;

/**
 * Relays a fixed set of calls from a loopback development clone to its source instance.
 * The browser signs every request; this class only forwards and never sees a private key.
 * Session cookies live in this object, so they end with the local request that created it.
 */
final class LocalDevelopmentRemote
{
    /** Exact paths this relay may call. Nothing else is forwarded. */
    public const PATHS = [
        'GET /api/get_username',
        'GET /api/get_profile',
        'GET /api/auth_challenge',
        'POST /api/prepare_identity',
        'POST /api/create_identity',
        'POST /api/authenticate_identity',
        'GET /api/private_messages/export',
        'POST /api/private_messages/history_sync/acknowledge',
    ];

    /** @var array<string, string> */
    private array $cookies = [];
    /** @var \Closure(string, string, list<string>, string): array{status:int,headers:list<string>,body:string} */
    private \Closure $transport;

    public function __construct(private readonly string $source, ?\Closure $transport = null)
    {
        $parts = InstanceArchiveDownloader::validateUrl($source);
        if (isset($parts['query']) || (isset($parts['path']) && trim($parts['path'], '/') !== '')) {
            throw new RuntimeException('Source must be the instance address only, such as https://zenmemes.com.');
        }
        $this->transport = $transport ?? self::streamTransport(...);
    }

    public function source(): string
    {
        return rtrim($this->source, '/');
    }

    /** Approved keys under this username on the source. */
    public function approvedKeyCount(string $username): int
    {
        $response = $this->call('GET', '/api/get_username', ['username' => $username]);
        if ($response['status'] === 400) return 0;
        $this->assertOk($response, 'Could not check the username on ' . $this->source() . '.');
        if (preg_match('/^Approved-Keys: (\d+)$/m', $response['body'], $match) !== 1) {
            throw new RuntimeException($this->source() . ' sent an unexpected username response.');
        }
        return (int) $match[1];
    }

    /** Whether the source knows this key, and whether it is approved there. @return array{registered:bool,approved:bool} */
    public function keyState(string $identityId): array
    {
        $response = $this->call('GET', '/api/get_profile', ['profile_slug' => str_replace(':', '-', $identityId)]);
        if ($response['status'] === 404) return ['registered' => false, 'approved' => false];
        $this->assertOk($response, 'Could not check the key on ' . $this->source() . '.');
        return ['registered' => true, 'approved' => preg_match('/^Approved: (?:yes|1|true)$/mi', $response['body']) === 1];
    }

    /** @return array<string, mixed> canonical_record and prepare_token, or exists => true */
    public function prepareIdentity(string $publicKey): array
    {
        $result = $this->json($this->call('POST', '/api/prepare_identity', [], ['public_key' => $publicKey]));
        if (($result['status'] ?? '') !== 'ok' && ($result['error'] ?? '') === 'Identity already exists for this fingerprint.') {
            return ['exists' => true];
        }
        return $this->okOrThrow($result, 'Could not prepare the key on ' . $this->source() . '.');
    }

    /** @return array<string, mixed> */
    public function createIdentity(string $prepareToken, string $canonicalRecord, string $signature): array
    {
        $result = $this->json($this->call('POST', '/api/create_identity', [], [
            'prepare_token' => $prepareToken, 'canonical_record' => $canonicalRecord, 'detached_signature' => $signature,
        ]));
        if (($result['status'] ?? '') !== 'ok' && ($result['error'] ?? '') === 'Identity already exists for this fingerprint.') {
            return ['exists' => true];
        }
        return $this->okOrThrow($result, 'Could not register the key on ' . $this->source() . '.');
    }

    public function challenge(): string
    {
        $response = $this->call('GET', '/api/auth_challenge');
        $this->assertOk($response, 'Could not get a sign-in challenge from ' . $this->source() . '.');
        if (preg_match('/^challenge=([a-f0-9]+)$/m', $response['body'], $match) !== 1) {
            throw new RuntimeException($this->source() . ' sent an unexpected sign-in challenge.');
        }
        return $match[1];
    }

    /** Signs in for the rest of this object's life. @return array{approved:bool} */
    public function signIn(string $identityId, string $challenge, string $signature): array
    {
        $response = $this->call('POST', '/api/authenticate_identity', [], [
            'identity_id' => $identityId, 'challenge' => $challenge, 'detached_signature' => $signature,
        ]);
        if ($response['status'] !== 200) {
            $error = preg_match('/^error=(.+)$/m', $response['body'], $match) === 1 ? trim($match[1]) : 'Sign-in failed.';
            throw new RuntimeException($this->source() . ': ' . $error);
        }
        return ['approved' => preg_match('/^approved=1$/m', $response['body']) === 1];
    }

    /** @return array<string, mixed> */
    public function export(string $afterTime = '', string $afterId = ''): array
    {
        $query = $afterTime === '' ? [] : ['after_time' => $afterTime, 'after_id' => $afterId];
        $response = $this->call('GET', '/api/private_messages/export', $query);
        if ($response['status'] === 404) {
            throw new RuntimeException($this->source() . ' does not support pulling messages yet. Deploy a newer version there, then retry.');
        }
        return $this->okOrThrow($this->json($response), 'Could not read messages from ' . $this->source() . '.');
    }

    /** @param list<array{message_id:string,digest:string,transfer_id:string}> $items @return array<string, mixed> */
    public function acknowledge(array $items): array
    {
        $response = $this->call('POST', '/api/private_messages/history_sync/acknowledge', [], null,
            json_encode(['items' => $items, 'mode' => 'account'], JSON_THROW_ON_ERROR));
        return $this->okOrThrow($this->json($response), 'Could not confirm history on ' . $this->source() . '.');
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string>|null $form
     * @return array{status:int,headers:list<string>,body:string}
     */
    private function call(string $method, string $path, array $query = [], ?array $form = null, ?string $json = null): array
    {
        if (!in_array($method . ' ' . $path, self::PATHS, true)) {
            throw new RuntimeException('This relay does not forward ' . $method . ' ' . $path . '.');
        }
        $url = $this->source() . $path . ($query === [] ? '' : '?' . http_build_query($query));
        $headers = ['Accept: application/json, text/plain', 'User-Agent: ForumLocalPull/1'];
        $body = '';
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'X-Requested-With: ForumPrivateMessages';
            $body = $json;
        } elseif ($form !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $body = http_build_query($form);
        }
        if ($this->cookies !== []) {
            $headers[] = 'Cookie: ' . implode('; ', array_map(static fn (string $name, string $value): string => $name . '=' . $value,
                array_keys($this->cookies), $this->cookies));
        }
        $response = ($this->transport)($method, $url, $headers, $body);
        foreach ($response['headers'] as $header) {
            if (preg_match('/^Set-Cookie:\s*([^=;\s]+)=([^;]*)/i', $header, $match) === 1) {
                $this->cookies[$match[1]] = $match[2];
            }
        }
        return $response;
    }

    /** @return array<string, mixed> */
    private function json(array $response): array
    {
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new RuntimeException($this->source() . ' sent an unexpected response (HTTP ' . $response['status'] . ').');
        }
        return $decoded;
    }

    /** @return array<string, mixed> */
    private function okOrThrow(array $result, string $fallback): array
    {
        if (($result['status'] ?? '') === 'ok') return $result;
        throw new RuntimeException(is_string($result['error'] ?? null) && $result['error'] !== '' ? $this->source() . ': ' . $result['error'] : $fallback);
    }

    private function assertOk(array $response, string $message): void
    {
        if ($response['status'] !== 200) throw new RuntimeException($message . ' (HTTP ' . $response['status'] . ')');
    }

    /** @param list<string> $headers @return array{status:int,headers:list<string>,body:string} */
    private static function streamTransport(string $method, string $url, array $headers, string $body): array
    {
        $context = stream_context_create([
            'http' => ['method' => $method, 'header' => implode("\r\n", $headers) . "\r\n", 'content' => $body,
                'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 20],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
        ]);
        $content = @file_get_contents($url, false, $context, 0, 8 * 1048576);
        $responseHeaders = $http_response_header ?? [];
        if ($content === false || $responseHeaders === []) {
            throw new RuntimeException('Could not reach ' . (parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST)) . '. Check the connection and retry.');
        }
        $status = preg_match('#^HTTP/\S+ (\d{3})#', (string) $responseHeaders[0], $match) === 1 ? (int) $match[1] : 0;
        return ['status' => $status, 'headers' => array_slice($responseHeaders, 1), 'body' => $content];
    }
}
