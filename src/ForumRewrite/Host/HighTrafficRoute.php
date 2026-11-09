<?php

declare(strict_types=1);
namespace ForumRewrite\Host;

/**
 * One canonical allowlist shared by HTTP, cron and cache admission.
 *
 * Containers end in a slash (`/`, `/threads/`, `/about/`); a thread page is a
 * leaf and does not (`/threads/{id}`), matching every internal link so that
 * normal navigation and crawls never hit a redirect.
 */
final class HighTrafficRoute
{
    public static function canonical(string $uri): ?string
    {
        if (str_contains($uri, '?') || str_contains($uri, '#') || str_contains($uri, '%')) return null;
        if (in_array($uri, ['/', '/threads/', '/about/'], true)) return $uri;
        return preg_match('~^/threads/[a-zA-Z0-9][a-zA-Z0-9_-]{0,127}$~D', $uri) === 1 ? $uri : null;
    }

    /** Canonical route for a non-canonical alias that differs only by a trailing slash, else null. */
    public static function alias(string $uri): ?string
    {
        if (self::canonical($uri) !== null || str_contains($uri, '?')) return null;
        $alternate = str_ends_with($uri, '/') ? rtrim($uri, '/') : $uri . '/';
        return $alternate === '' ? null : self::canonical($alternate);
    }

    public static function file(string $route): string
    {
        if (self::canonical($route) === null) throw new \InvalidArgumentException('Ineligible high-traffic route.');
        return $route === '/' ? 'index.html' : rtrim(ltrim($route, '/'), '/') . '/index.html';
    }
}
