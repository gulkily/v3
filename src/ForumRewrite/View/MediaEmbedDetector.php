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
     * @return list<array{provider: string, url: string, displayUrl: string, embedId: string, offset: int, length: int}>
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

            $matches[] = [
                'provider' => $classified['provider'],
                'url' => $url,
                'displayUrl' => $this->stripTrackingParams($url),
                'embedId' => $classified['embedId'],
                'offset' => (int) $offset,
                'length' => strlen($url),
            ];
        }

        return $matches;
    }

    /**
     * @return ?array{provider: string, embedId: string}
     */
    private function classify(string $url): ?array
    {
        if (preg_match('#^https?://(?:www\.)?youtube\.com/watch\?(?:[^\s&]*&)*v=([A-Za-z0-9_-]{6,})#i', $url, $matches) === 1) {
            return ['provider' => 'youtube', 'embedId' => $matches[1]];
        }

        if (preg_match('#^https?://youtu\.be/([A-Za-z0-9_-]{6,})#i', $url, $matches) === 1) {
            return ['provider' => 'youtube', 'embedId' => $matches[1]];
        }

        if (preg_match('#^https?://(?:www\.)?instagram\.com/(?:p|reel)/([A-Za-z0-9_-]+)/?#i', $url, $matches) === 1) {
            return ['provider' => 'instagram', 'embedId' => $matches[1]];
        }

        return null;
    }

    private function stripTrackingParams(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $queryParams);

        foreach (array_keys($queryParams) as $key) {
            if (in_array($key, self::TRACKING_PARAMS, true) || str_starts_with($key, 'utm_')) {
                unset($queryParams[$key]);
            }
        }

        $query = http_build_query($queryParams);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . '://' . $host . $path . ($query !== '' ? '?' . $query : '') . $fragment;
    }
}
