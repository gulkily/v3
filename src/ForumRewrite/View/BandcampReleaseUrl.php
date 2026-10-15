<?php

declare(strict_types=1);
namespace ForumRewrite\View;

/** The single acceptance boundary shared by detection and player discovery. */
final class BandcampReleaseUrl
{
    /** @return ?array{url: string, kind: string} */
    public static function parse(string $url): ?array
    {
        $p = parse_url($url);
        if ($p === false || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)
            || isset($p['user']) || isset($p['pass']) || isset($p['port'])
            || preg_match('/[\s<>"\\\\]/', $url)) {
            return null;
        }
        $host = strtolower($p['host'] ?? '');
        if (!preg_match('/^([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)\.bandcamp\.com$/D', $host, $h)
            || in_array($h[1], ['www', 'daily', 'get', 'help', 'support', 'api', 'blog', 'blogstuff', 'artists', 'labels', 'merch', 'discover', 'search'], true)
            || !preg_match('#^/(album|track)/[a-z0-9]+(?:-[a-z0-9]+)*/?$#D', $p['path'] ?? '', $m)) {
            return null;
        }
        foreach (['query', 'fragment'] as $part) {
            foreach (explode('&', $p[$part] ?? '') as $pair) {
                if (in_array(strtolower(urldecode(explode('=', $pair, 2)[0])), ['secret_token', 'secret-token', 'token', 'access_token', 'private'], true)) {
                    return null;
                }
            }
        }
        return ['url' => 'https://' . $host . rtrim($p['path'], '/'), 'kind' => $m[1]];
    }
}
