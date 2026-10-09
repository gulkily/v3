<?php

declare(strict_types=1);

namespace ForumRewrite\View;

/**
 * Fetches a public Instagram post's page and parses only its og:title/og:image
 * meta tags - never its oEmbed html/script, and never anything else from the
 * page. Instagram's oEmbed endpoint stopped returning thumbnail_url/author_name
 * in late 2025; this is Meta's own suggested replacement for that case.
 */
final class InstagramPagePreviewFetcher
{
    private const TIMEOUT_SECONDS = 3;

    /** @var (callable(string): (string|false))|null */
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport;
    }

    /**
     * @return ?array{title: string, thumbnailUrl: string}
     */
    public function fetch(string $url): ?array
    {
        $html = $this->fetchPage($url);

        if ($html === false || $html === '') {
            return null;
        }

        $title = $this->extractMetaContent($html, 'og:title');
        $thumbnailUrl = $this->extractMetaContent($html, 'og:image');

        if ($title === null || $thumbnailUrl === null) {
            return null;
        }

        return ['title' => $title, 'thumbnailUrl' => $thumbnailUrl];
    }

    private function fetchPage(string $url): string|false
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

    private function extractMetaContent(string $html, string $property): ?string
    {
        $escapedProperty = preg_quote($property, '#');

        $patterns = [
            '#<meta\s+property=["\']' . $escapedProperty . '["\']\s+content=["\']([^"\']*)["\']#i',
            '#<meta\s+content=["\']([^"\']*)["\']\s+property=["\']' . $escapedProperty . '["\']#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                $value = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}
