<?php

declare(strict_types=1);
namespace ForumRewrite\Host;

/** One canonical allowlist shared by HTTP, cron and cache admission. */
final class HighTrafficRoute
{
    public static function canonical(string $uri): ?string
    {
        if (str_contains($uri, '?') || str_contains($uri, '#') || str_contains($uri, '%')) return null;
        if (in_array($uri, ['/', '/threads/', '/about/'], true)) return $uri;
        return preg_match('~^/threads/[a-zA-Z0-9][a-zA-Z0-9_-]{0,127}/$~D', $uri) === 1 ? $uri : null;
    }

    public static function file(string $route): string
    {
        if (self::canonical($route) === null) throw new \InvalidArgumentException('Ineligible high-traffic route.');
        return $route === '/' ? 'index.html' : ltrim($route, '/') . 'index.html';
    }
}
