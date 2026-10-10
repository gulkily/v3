<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use Closure;
use RuntimeException;

final class InstanceArchiveDownloader
{
    public function __construct(
        private readonly int $maxBytes = 268435456,
        private readonly int $timeoutSeconds = 120,
        private readonly ?Closure $progress = null,
    ) {
    }

    public static function validateUrl(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            throw new RuntimeException('Source must be an HTTP(S) URL without credentials, fragments, or whitespace.');
        }
        return $parts;
    }

    public function download(string $instanceUrl, string $destination): int
    {
        $parts = self::validateUrl($instanceUrl);
        if (isset($parts['query'])) {
            throw new RuntimeException('Instance URL must not contain a query.');
        }
        $url = rtrim($instanceUrl, '/') . '/downloads/repository.tar.gz';
        $deadline = microtime(true) + $this->timeoutSeconds;
        $output = null;
        $created = false;
        try {
            for ($redirect = 0; $redirect <= 5; $redirect++) {
                self::validateUrl($url);
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) {
                    throw new RuntimeException('Archive download timed out.');
                }
                $context = stream_context_create([
                    'http' => ['method' => 'GET', 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => min(20, $remaining),
                        'header' => "Accept: application/gzip\r\nAccept-Encoding: identity\r\nConnection: close\r\nUser-Agent: ForumInstanceImport/1\r\n"],
                    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
                ]);
                $input = @fopen($url, 'rb', false, $context);
                if ($input === false) {
                    throw new RuntimeException('Unable to download source archive (connection, timeout, or TLS failure).');
                }
                try {
                    $metadata = stream_get_meta_data($input);
                    $headers = $metadata['wrapper_data'] ?? [];
                    $status = 0;
                    $values = [];
                    foreach ($headers as $header) {
                        if (preg_match('#^HTTP/\S+ (\d{3})#', $header, $match)) {
                            $status = (int) $match[1];
                            $values = [];
                        } elseif (str_contains($header, ':')) {
                            [$key, $value] = explode(':', $header, 2);
                            $values[strtolower(trim($key))] = trim($value);
                        }
                    }
                    if (in_array($status, [301, 302, 303, 307, 308], true)) {
                        if ($redirect === 5 || !isset($values['location'])) {
                            throw new RuntimeException('Archive redirect limit exceeded or missing Location.');
                        }
                        $next = $this->redirectUrl($url, $values['location']);
                        $nextParts = self::validateUrl($next);
                        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' && strtolower($nextParts['scheme']) !== 'https') {
                            throw new RuntimeException('Refusing HTTPS-to-HTTP archive redirect.');
                        }
                        $url = $next;
                        continue;
                    }
                    if ($status !== 200) {
                        throw new RuntimeException('Source archive returned HTTP ' . $status . '; public repository download is required.');
                    }
                    if (isset($values['content-encoding']) && strtolower($values['content-encoding']) !== 'identity') {
                        throw new RuntimeException('Unsupported archive transport encoding.');
                    }
                    $length = $values['content-length'] ?? null;
                    if ($length !== null && (!ctype_digit($length) || (float) $length > $this->maxBytes)) {
                        throw new RuntimeException('Archive exceeds download size limit or has invalid Content-Length.');
                    }
                    $output = @fopen($destination, 'xb');
                    if ($output === false) {
                        throw new RuntimeException('Unable to create archive download file.');
                    }
                    $created = true;
                    $count = 0;
                    $lastProgress = 0;
                    while (!feof($input)) {
                        $remaining = $deadline - microtime(true);
                        if ($remaining <= 0) { throw new RuntimeException('Archive download timed out.'); }
                        $wait = min(20.0, $remaining);
                        stream_set_timeout($input, (int) $wait, (int) (($wait - (int) $wait) * 1000000));
                        $chunk = fread($input, 65536);
                        $metadata = stream_get_meta_data($input);
                        if ($chunk === false || ($metadata['timed_out'] ?? false) || microtime(true) > $deadline) {
                            throw new RuntimeException('Archive download interrupted or timed out.');
                        }
                        $count += strlen($chunk);
                        if ($count > $this->maxBytes) {
                            throw new RuntimeException('Archive exceeds download size limit.');
                        }
                        if (fwrite($output, $chunk) !== strlen($chunk)) {
                            throw new RuntimeException('Unable to write archive download.');
                        }
                        if ($this->progress !== null && $count - $lastProgress >= 1048576) {
                            ($this->progress)('Downloaded ' . $count . ' bytes...');
                            $lastProgress = $count;
                        }
                    }
                    if ($count === 0 || ($length !== null && !isset($values['transfer-encoding']) && (int) $length !== $count)) {
                        throw new RuntimeException('Empty or truncated archive download.');
                    }
                    fclose($output);
                    $output = null;
                    return $count;
                } finally {
                    fclose($input);
                }
            }
            throw new RuntimeException('Archive redirect limit exceeded.');
        } catch (\Throwable $error) {
            if (is_resource($output)) {
                fclose($output);
            }
            if ($created && is_file($destination)) {
                unlink($destination);
            }
            throw $error;
        }
    }

    private function redirectUrl(string $url, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) {
            return $location;
        }
        $parts = self::validateUrl($url);
        if (str_starts_with($location, '//')) {
            return $parts['scheme'] . ':' . $location;
        }
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (str_starts_with($location, '?')) {
            return $origin . ($parts['path'] ?? '/') . $location;
        }
        $path = str_starts_with($location, '/') ? $location : dirname($parts['path'] ?? '/') . '/' . $location;
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '' && $segment !== '.') {
                $segments[] = $segment;
            }
        }
        return $origin . '/' . implode('/', $segments);
    }
}
