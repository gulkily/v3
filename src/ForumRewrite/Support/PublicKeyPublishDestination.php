<?php
declare(strict_types=1);

namespace ForumRewrite\Support;

use ForumRewrite\Import\InstanceSourceResolver;
use ForumRewrite\Security\OpenPgpKeyInspector;
use RuntimeException;

/** Only the existing public profile and registration endpoints, without redirects or credentials. */
final class PublicKeyPublishDestination
{
    public readonly string $url;

    public function __construct(string $url, private readonly int $timeout = 10)
    {
        if ($timeout < 1 || $timeout > 120) {
            throw new RuntimeException('Use a timeout from 1 to 120 seconds.');
        }
        try {
            $this->url = (new InstanceSourceResolver())->resolve($url);
        } catch (RuntimeException) {
            throw new RuntimeException('Destination must be a known instance name, hostname or HTTP(S) URL without credentials, a query, fragment or whitespace. Run ./v3 publish-keys --help.');
        }
    }

    public function profile(string $fingerprint): array
    {
        $identity = 'openpgp:' . strtolower($fingerprint);
        try {
            [$status, $body, $type] = $this->request('/api/get_profile?profile_slug=openpgp-' . strtolower($fingerprint));
            if (in_array($status, [401, 403], true)) {
                return ['state' => 'refused', 'detail' => 'Destination access refused; use an instance that permits public registration.'];
            }
            if (!str_starts_with($type, 'text/plain')) {
                throw new RuntimeException('Unexpected profile response; check the destination and retry.');
            }
            if ($status === 404 && $body === "profile not found\n") {
                return ['state' => 'missing', 'detail' => 'Not registered'];
            }
            if ($status !== 200) {
                throw new RuntimeException('Profile HTTP ' . $status . '; check the destination and retry.');
            }
            $fields = [];
            foreach (explode("\n", trim($body)) as $line) {
                if (!str_contains($line, ': ')) continue;
                [$key, $value] = explode(': ', $line, 2);
                if (in_array($key, ['Identity-ID', 'Profile-Slug', 'Username', 'Retired', 'Approved'], true)) {
                    if (isset($fields[$key])) throw new RuntimeException('Duplicate profile field.');
                    $fields[$key] = $value;
                }
            }
            if (($fields['Identity-ID'] ?? '') !== $identity
                || ($fields['Profile-Slug'] ?? '') !== str_replace(':', '-', $identity)
                || !preg_match('/^[a-z0-9._-]{1,80}$/D', $fields['Username'] ?? '')
                || !in_array($fields['Retired'] ?? '', ['yes', 'no'], true)
                || !in_array($fields['Approved'] ?? '', ['yes', 'no'], true)) {
                throw new RuntimeException('Incomplete or mismatched profile; check the destination and retry.');
            }
            return ['state' => $fields['Retired'] === 'yes' ? 'refused' : 'existing',
                'name' => $fields['Username'], 'approved' => $fields['Approved'],
                'detail' => $fields['Retired'] === 'yes' ? 'Destination key is retired' : 'Already registered'];
        } catch (RuntimeException $error) {
            return ['state' => 'unknown', 'detail' => $error->getMessage()];
        }
    }

    /** A registration response is only a hint; the caller must read the identity back. */
    public function register(string $publicKey): array
    {
        (new OpenPgpKeyInspector())->inspectPublicOnly($publicKey);
        try {
            [$status, $body, $type] = $this->request('/api/link_identity', ['public_key' => $publicKey]);
            return ['refused' => in_array($status, [400, 401, 403, 405], true),
                'detail' => $status === 200 && str_starts_with($type, 'text/plain') && str_starts_with($body, "status=ok\n")
                    ? 'Registration answered; verifying destination' : 'Registration HTTP ' . $status . '; check destination access and retry.'];
        } catch (RuntimeException $error) {
            return ['refused' => false, 'detail' => $error->getMessage()];
        }
    }

    private function request(string $path, ?array $fields = null): array
    {
        $deadline = microtime(true) + $this->timeout;
        $context = stream_context_create([
            'http' => ['method' => $fields === null ? 'GET' : 'POST', 'timeout' => $this->timeout,
                'ignore_errors' => true, 'follow_location' => 0,
                'header' => "Accept: text/plain\r\nConnection: close\r\n" . ($fields === null ? '' : "Content-Type: application/x-www-form-urlencoded\r\n"),
                'content' => $fields === null ? '' : http_build_query($fields, '', '&')],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
        ]);
        $stream = @fopen($this->url . $path, 'rb', false, $context);
        if ($stream === false) throw new RuntimeException('Connection or TLS failure; check the destination and retry.');
        try {
            $metadata = stream_get_meta_data($stream);
            $status = 0; $type = '';
            foreach ($metadata['wrapper_data'] ?? [] as $header) {
                if (preg_match('#^HTTP/\S+ (\d{3})#', $header, $match)) { $status = (int) $match[1]; $type = ''; }
                elseif (stripos($header, 'Content-Type:') === 0) $type = strtolower(trim(substr($header, 13)));
            }
            $body = '';
            while (!feof($stream)) {
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) throw new RuntimeException('Response timed out; retry later.');
                stream_set_timeout($stream, (int) $remaining, (int) (($remaining - (int) $remaining) * 1000000));
                $chunk = fread($stream, min(8192, 65537 - strlen($body)));
                if ($chunk === false || (stream_get_meta_data($stream)['timed_out'] ?? false)) {
                    throw new RuntimeException('Response timed out; retry later.');
                }
                $body .= $chunk;
                if (strlen($body) > 65536) throw new RuntimeException('Response exceeds size limit; check the destination.');
            }
            return [$status, $body, $type];
        } finally { fclose($stream); }
    }
}
