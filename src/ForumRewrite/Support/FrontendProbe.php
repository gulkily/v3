<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

final class FrontendProbe
{
    /** @return array{status:string,detail:string,latency_ms:?int} */
    public function probe(string $url, float $timeoutSeconds = 3.0, ?string $expectedIdentity = null): array
    {
        try {
            FrontendTargetResolver::validateUrl($url);
        } catch (\RuntimeException) {
            return $this->result('unavailable', 'invalid URL');
        }
        $parts = parse_url($url);
        $host = trim($parts['host'], '[]');
        $secure = $parts['scheme'] === 'https';
        $port = (int) ($parts['port'] ?? ($secure ? 443 : 80));
        $authority = (str_contains($host, ':') ? '[' . $host . ']' : $host)
            . (isset($parts['port']) ? ':' . $port : '');
        $path = $expectedIdentity !== null ? FrontendInstance::PATH : (($parts['path'] ?? '') ?: '/');
        if ($expectedIdentity === null && isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }
        $start = hrtime(true);
        $deadline = $start / 1e9 + $timeoutSeconds;
        if ($timeoutSeconds <= 0) {
            return $this->result('timed_out', 'frontend probe budget exhausted');
        }
        // Resolve hostnames in a bounded child process: libc DNS can exceed socket timeouts.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $address = $host;
        } else {
            $address = $this->resolveHost($host, $deadline);
            if ($address === null) {
                return $this->result('timed_out', 'name resolution failed or exceeded probe budget');
            }
        }
        $remaining = $deadline - hrtime(true) / 1e9;
        if ($remaining <= 0) {
            return $this->result('timed_out', 'frontend probe budget exhausted');
        }
        $context = stream_context_create(['ssl' => ['peer_name' => $host, 'SNI_enabled' => true, 'verify_peer' => true, 'verify_peer_name' => true]]);
        $errorCode = 0;
        $errorMessage = '';
        $socket = @stream_socket_client(
            ($secure ? 'ssl://' : 'tcp://') . (str_contains($address, ':') ? '[' . $address . ']' : $address) . ':' . $port,
            $errorCode, $errorMessage, $remaining, STREAM_CLIENT_CONNECT, $context,
        );
        if ($socket === false) {
            return hrtime(true) / 1e9 >= $deadline - 0.05
                ? $this->result('timed_out', 'connect timed out after ' . $this->seconds($timeoutSeconds))
                : $this->result('not_running', 'connection refused or unreachable (' . trim($errorMessage) . ')');
        }
        try {
            stream_set_blocking($socket, false);
            $request = 'GET ' . $path . " HTTP/1.0\r\nHost: " . $authority . "\r\nConnection: close\r\n\r\n";
            $response = '';
            while (hrtime(true) / 1e9 < $deadline && strlen($response) < 16384) {
                if ($request !== '') {
                    $written = @fwrite($socket, $request);
                    if ($written === false) { break; }
                    $request = substr($request, $written);
                }
                $chunk = @fread($socket, 2048);
                if ($chunk !== false) { $response .= $chunk; }
                if (str_contains($response, "\r\n\r\n") || feof($socket)) { break; }
                usleep(1000);
            }
        } finally {
            fclose($socket);
        }
        $elapsed = (int) round((hrtime(true) - $start) / 1e6);
        if (preg_match('#^HTTP/\d(?:\.\d)? (\d{3})#', $response, $matches) === 1 && str_contains($response, "\r\n\r\n")) {
            $code = (int) $matches[1];
            if ($expectedIdentity !== null && ($code !== 204
                || preg_match('/\r\nX-V3-Instance: ([a-f0-9]{64})\r\n/i', $response, $identity) !== 1
                || !hash_equals($expectedIdentity, $identity[1]))) {
                return $this->result('unavailable', 'instance identity could not be verified');
            }
            if ($code >= 300 && $code < 400 && !$this->sameOriginRedirect($response, $parts)) {
                return $this->result('redirected', 'HTTP ' . $code . ' redirects outside the configured origin');
            }
            return ['status' => $code >= 500 ? 'error' : 'responding', 'detail' => 'HTTP ' . $code . ' in ' . $elapsed . ' ms', 'latency_ms' => $elapsed];
        }
        return $this->result('timed_out', 'no response within ' . $this->seconds($timeoutSeconds) . ' (server busy or hung)');
    }

    private function sameOriginRedirect(string $response, array $origin): bool
    {
        if (preg_match('/\r\nLocation: ([^\r\n]*)\r\n/i', $response, $match) !== 1) { return false; }
        $location = parse_url(trim($match[1]));
        if (!is_array($location) || isset($location['user']) || isset($location['pass'])) { return false; }
        $scheme = $location['scheme'] ?? $origin['scheme'];
        $host = $location['host'] ?? $origin['host'];
        $port = $location['port'] ?? (isset($location['host']) ? ($scheme === 'https' ? 443 : 80) : ($origin['port'] ?? ($origin['scheme'] === 'https' ? 443 : 80)));
        return $scheme === $origin['scheme'] && strtolower($host) === strtolower($origin['host'])
            && $port === ($origin['port'] ?? ($origin['scheme'] === 'https' ? 443 : 80));
    }

    private function resolveHost(string $host, float $deadline): ?string
    {
        if (!function_exists('proc_open')) { return null; }
        $process = @proc_open([PHP_BINARY, '-r', '$ip = gethostbyname($argv[1]); if ($ip === $argv[1]) { $records = dns_get_record($argv[1], DNS_AAAA); $ip = $records[0]["ipv6"] ?? ""; } echo $ip;', $host], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { return null; }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        $output = '';
        try {
            do {
                $output .= stream_get_contents($pipes[1]);
                if (!proc_get_status($process)['running']) { break; }
                usleep(1000);
            } while (hrtime(true) / 1e9 < $deadline);
            $output .= stream_get_contents($pipes[1]);
        } finally {
            proc_terminate($process, 9);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
        return filter_var($output, FILTER_VALIDATE_IP) ? $output : null;
    }

    private function result(string $status, string $detail): array
    {
        return ['status' => $status, 'detail' => $detail, 'latency_ms' => null];
    }

    private function seconds(float $seconds): string
    {
        return rtrim(rtrim(number_format($seconds, 1), '0'), '.') . 's';
    }
}
