<?php

declare(strict_types=1);

namespace ForumRewrite\View;

/**
 * Fetches a music link's title from its provider's keyless public oEmbed
 * endpoint (Spotify, SoundCloud, TikTok), the same way
 * YoutubeOembedTitleFetcher does for YouTube. Any failure returns null:
 * the thread keeps its link as the title and the preview cache retries later.
 */
final class OembedTitleFetcher
{
    private const TIMEOUT_SECONDS = 3;
    private const MAX_BYTES = 262144;

    private const ENDPOINTS = [
        'spotify' => 'https://open.spotify.com/oembed?url=',
        'soundcloud' => 'https://soundcloud.com/oembed?format=json&url=',
        'tiktok' => 'https://www.tiktok.com/oembed?url=',
    ];

    /** @var (callable(string): (string|false))|null */
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport;
    }

    public static function supports(string $provider): bool
    {
        return isset(self::ENDPOINTS[$provider]);
    }

    /** @return ?array{title: string, thumbnailUrl: ?string} */
    public function fetch(string $provider, string $url): ?array
    {
        $endpoint = self::ENDPOINTS[$provider] ?? null;
        if ($endpoint === null) {
            return null;
        }

        $json = $this->fetchJson($endpoint . rawurlencode($url));
        if ($json === false || $json === '' || strlen($json) > self::MAX_BYTES) {
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        $title = $decoded['title'] ?? null;
        if (!is_string($title) || trim($title) === '') {
            return null;
        }

        $thumbnailUrl = $decoded['thumbnail_url'] ?? null;

        return [
            'title' => trim($title),
            'thumbnailUrl' => is_string($thumbnailUrl) && $thumbnailUrl !== '' ? $thumbnailUrl : null,
        ];
    }

    private function fetchJson(string $url): string|false
    {
        if ($this->transport !== null) {
            return ($this->transport)($url);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT_SECONDS,
                'header' => "User-Agent: Mozilla/5.0 (compatible)\r\n",
                'ignore_errors' => true,
            ],
        ]);

        return @file_get_contents($url, false, $context, 0, self::MAX_BYTES + 1);
    }
}
