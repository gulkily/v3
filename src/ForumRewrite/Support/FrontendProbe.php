<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

final class FrontendProbe
{
    /**
     * @return array{status:string,detail:string,latency_ms:?int}
     */
    public function probe(string $url, float $timeoutSeconds = 3.0): array
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
        if ($host === '') {
            return ['status' => 'unavailable', 'detail' => 'invalid URL: ' . $url, 'latency_ms' => null];
        }
        $secure = ($parts['scheme'] ?? 'http') === 'https';
        $port = (int) ($parts['port'] ?? ($secure ? 443 : 80));
        $path = ($parts['path'] ?? '') === '' ? '/' : (string) $parts['path'];

        $start = hrtime(true);
        $errorCode = 0;
        $errorMessage = '';
        $socket = @stream_socket_client(
            ($secure ? 'ssl://' : 'tcp://') . $host . ':' . $port,
            $errorCode,
            $errorMessage,
            $timeoutSeconds,
        );
        if ($socket === false) {
            $elapsed = $this->elapsedMilliseconds($start);
            $timedOut = $elapsed >= (int) ($timeoutSeconds * 1000) - 50;

            return $timedOut
                ? ['status' => 'timed_out', 'detail' => 'connect timed out after ' . $this->seconds($timeoutSeconds), 'latency_ms' => null]
                : ['status' => 'not_running', 'detail' => 'connection refused or unreachable (' . trim($errorMessage) . ')', 'latency_ms' => null];
        }

        try {
            stream_set_timeout($socket, 0, 100000);
            fwrite($socket, 'GET ' . $path . " HTTP/1.0\r\nHost: " . $host . "\r\nConnection: close\r\n\r\n");
            $line = '';
            // Poll in short reads so the overall deadline is hard even if the server trickles bytes.
            while (!str_contains($line, "\n") && (hrtime(true) - $start) / 1e9 < $timeoutSeconds) {
                $chunk = fgets($socket, 256);
                if ($chunk === false) {
                    if (feof($socket)) {
                        break;
                    }
                    continue;
                }
                $line .= $chunk;
            }
        } finally {
            fclose($socket);
        }

        $elapsed = $this->elapsedMilliseconds($start);
        if (preg_match('#^HTTP/\d\.\d (\d{3})#', $line, $matches) === 1) {
            $code = (int) $matches[1];

            return $code >= 500
                ? ['status' => 'error', 'detail' => 'HTTP ' . $code . ' in ' . $elapsed . ' ms', 'latency_ms' => $elapsed]
                : ['status' => 'responding', 'detail' => 'HTTP ' . $code . ' in ' . $elapsed . ' ms', 'latency_ms' => $elapsed];
        }

        return ['status' => 'timed_out', 'detail' => 'no response within ' . $this->seconds($timeoutSeconds) . ' (server busy or hung)', 'latency_ms' => null];
    }

    private function elapsedMilliseconds(int $start): int
    {
        return (int) round((hrtime(true) - $start) / 1000000);
    }

    private function seconds(float $seconds): string
    {
        return rtrim(rtrim(number_format($seconds, 1), '0'), '.') . 's';
    }
}
