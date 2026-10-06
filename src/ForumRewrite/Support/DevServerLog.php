<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

// Formats request lines for the `./v3 start` dev server. public/router.php writes
// one tab-separated V3DEV line per request to stderr; scripts/dev_server.php reads
// them and prints the readable form below.
final class DevServerLog
{
    public const ROUTER_PREFIX = "V3DEV\t";
    public const GAP_SECONDS = 2;
    public const MODE_DEFAULT = 'default';
    public const MODE_VERBOSE = 'verbose';
    public const MODE_QUIET = 'quiet';

    private const RESET = "\e[0m";

    /**
     * @return array{status: int, method: string, uri: string, ms: float, why: string}|null
     */
    public static function parseRouterLine(string $line): ?array
    {
        $line = rtrim($line, "\r\n");
        if (!str_starts_with($line, self::ROUTER_PREFIX)) {
            return null;
        }

        $fields = explode("\t", substr($line, strlen(self::ROUTER_PREFIX)), 5);
        if (count($fields) !== 5) {
            return null;
        }

        [$status, $method, $uri, $ms, $why] = $fields;

        return [
            'status' => (int) $status,
            'method' => $method,
            'uri' => $uri,
            'ms' => (float) $ms,
            'why' => $why,
        ];
    }

    // Connection-level lines from PHP's built-in server. The router logs every
    // request itself, so its own access lines are dropped as well.
    public static function isBuiltInNoise(string $line): bool
    {
        return preg_match('/ (Accepted|Closing)$/', $line) === 1
            || str_contains($line, 'Development Server (')
            || preg_match('/^\[[^\]]+\] \S+ \[\d{3}\]: /', $line) === 1;
    }

    public static function isVisible(array $request, string $mode): bool
    {
        if ($mode === self::MODE_VERBOSE) {
            return true;
        }
        if ($mode === self::MODE_QUIET) {
            return $request['status'] >= 500;
        }

        return $request['why'] !== 'static';
    }

    public static function needsGapBefore(?int $previousAt, int $at): bool
    {
        return $previousAt !== null && $at - $previousAt > self::GAP_SECONDS;
    }

    public static function formatLine(array $request, string $port, int $at, bool $color): string
    {
        $status = (string) $request['status'];
        if ($color) {
            $status = self::statusColor($request['status']) . $status . self::RESET;
        }

        return rtrim(sprintf(
            '%s %s  %s  %-6s %7s  %s  %s',
            date('H:i:s', $at),
            $port,
            $status,
            $request['method'],
            sprintf('%dms', (int) round($request['ms'])),
            $request['uri'],
            $request['why'],
        ));
    }

    private static function statusColor(int $status): string
    {
        return match (intdiv($status, 100)) {
            2 => "\e[32m",
            3 => "\e[36m",
            4 => "\e[33m",
            default => "\e[31m",
        };
    }
}
