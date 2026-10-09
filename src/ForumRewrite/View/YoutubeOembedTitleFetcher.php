<?php

declare(strict_types=1);

namespace ForumRewrite\View;

/**
 * Fetches a YouTube video's title via its keyless public oEmbed endpoint.
 * Live availability of this endpoint could not be verified from this
 * sandbox (no outbound network access) - any failure here just returns
 * null, same graceful-degradation posture as InstagramPagePreviewFetcher.
 */
final class YoutubeOembedTitleFetcher
{
    private const TIMEOUT_SECONDS = 3;

    /** @var (callable(string): (string|false))|null */
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport;
    }

    /**
     * @return ?array{title: string, thumbnailUrl: ?string}
     */
    public function fetch(string $url): ?array
    {
        $oembedUrl = 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode($url);
        $json = $this->fetchJson($oembedUrl);

        if ($json === false || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        $title = $decoded['title'] ?? null;
        if (!is_string($title) || $title === '') {
            return null;
        }

        $thumbnailUrl = $decoded['thumbnail_url'] ?? null;

        return [
            'title' => $title,
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

        return @file_get_contents($url, false, $context);
    }
}
