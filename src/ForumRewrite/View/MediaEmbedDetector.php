<?php

declare(strict_types=1);

namespace ForumRewrite\View;

final class MediaEmbedDetector
{
    private const TRACKING_PARAMS = [
        'si',
        'igshid',
        'igsh',
        'fbclid',
    ];

    /**
     * @return list<array{provider: string, url: string, displayUrl: string, embedId: string, offset: int, length: int, contentKind?: string}>
     */
    public function detect(string $body): array
    {
        if (preg_match_all('/https?:\/\/[^\s<>"]+/i', $body, $found, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $matches = [];

        foreach ($found[0] as $found0) {
            [$rawUrl, $offset] = $found0;
            $url = rtrim((string) $rawUrl, ".,;:!?)'\"]");
            $classified = $this->classify($url);

            if ($classified === null) {
                continue;
            }

            $matches[] = $classified + [
                'url' => $url,
                'displayUrl' => $this->stripTrackingParams($url),
                'offset' => (int) $offset,
                'length' => strlen($url),
            ];
        }

        return $matches;
    }

    /**
     * Classifies a single already-isolated URL, independent of {@see detect()}'s
     * body-scanning. Exposed so callers that already hold a candidate URL (e.g.
     * the media-embed preview warm endpoint) can validate and extract its embed
     * identifier through the exact same rules, rather than re-implementing them.
     *
     * @return ?array{provider: string, embedId: string, contentKind?: string}
     */
    public function classify(string $url): ?array
    {
        $parts = parse_url($url);
        if ($parts === false || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || preg_match('/[\s<>"\\\\]/', $url) === 1) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        if (in_array($host, ['youtube.com', 'www.youtube.com'], true)) {
            if ($path === '/watch') {
                parse_str($parts['query'] ?? '', $query);
                $id = $query['v'] ?? null;
                if (is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $id) === 1) {
                    return ['provider' => 'youtube', 'embedId' => $id];
                }
            }
            if (preg_match('#^/shorts/([A-Za-z0-9_-]{11})/?$#D', $path, $matches) === 1) {
                return ['provider' => 'youtube', 'embedId' => $matches[1], 'contentKind' => 'shorts'];
            }
        }

        if ($host === 'youtu.be' && preg_match('#^/([A-Za-z0-9_-]{11})/?$#D', $path, $matches) === 1) {
            return ['provider' => 'youtube', 'embedId' => $matches[1]];
        }

        if (in_array($host, ['instagram.com', 'www.instagram.com'], true)
            && preg_match('#^/(?:p|reel)/([A-Za-z0-9_-]+)/?$#D', $path, $matches) === 1) {
            return ['provider' => 'instagram', 'embedId' => $matches[1]];
        }

        if ($host === 'open.spotify.com'
            && preg_match('#^/(track|album|playlist)/([A-Za-z0-9]{22})/?$#D', $path, $matches) === 1) {
            return ['provider' => 'spotify', 'embedId' => $matches[2], 'contentKind' => $matches[1]];
        }

        if (in_array($host, ['tiktok.com', 'www.tiktok.com'], true)
            && preg_match('#^/@[A-Za-z0-9_.]+/video/([0-9]{19})/?$#D', $path, $matches) === 1) {
            return ['provider' => 'tiktok', 'embedId' => $matches[1]];
        }

        if (in_array($host, ['soundcloud.com', 'www.soundcloud.com'], true)
            && !$this->hasPrivateMusicParameters($parts)
            && preg_match('#^/([A-Za-z0-9](?:[A-Za-z0-9_-]*[A-Za-z0-9])?)/([A-Za-z0-9](?:[A-Za-z0-9_-]*[A-Za-z0-9])?)/?$#D', $path, $m) === 1
            && !in_array(strtolower($m[1]), ['discover', 'search', 'stream', 'you', 'charts', 'stations', 'sets', 'tracks', 'playlists', 'tags', 'people', 'pages', 'jobs', 'terms-of-use', 'settings', 'upload', 'messages', 'notifications', 'connect', 'oauth', 'oembed'], true)
            && !in_array(strtolower($m[2]), ['sets', 'tracks', 'albums', 'reposts', 'likes', 'followers', 'following', 'popular-tracks'], true)) {
            return ['provider' => 'soundcloud', 'embedId' => $m[1] . '/' . $m[2], 'contentKind' => 'track'];
        }

        return null;
    }

    private function hasPrivateMusicParameters(array $parts): bool
    {
        foreach (['query', 'fragment'] as $part) {
            foreach (explode('&', $parts[$part] ?? '') as $pair) {
                $key = strtolower(urldecode(explode('=', $pair, 2)[0]));
                if (in_array($key, ['secret_token', 'secret-token', 'token', 'access_token', 'private'], true)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function stripTrackingParams(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['query'])) {
            return $url;
        }

        $kept = array_filter(explode('&', $parts['query']), static function (string $pair): bool {
            $key = urldecode(explode('=', $pair, 2)[0]);
            return !in_array($key, self::TRACKING_PARAMS, true) && !str_starts_with($key, 'utm_');
        });
        $query = implode('&', $kept);
        $queryStart = strpos($url, '?');
        $fragmentStart = strpos($url, '#', $queryStart);
        return substr($url, 0, $queryStart) . ($query !== '' ? '?' . $query : '')
            . ($fragmentStart === false ? '' : substr($url, $fragmentStart));
    }
}
