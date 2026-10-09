<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\View\YoutubeOembedTitleFetcher;

final class YoutubeOembedTitleFetcherTest
{
    public function testParsesTitleAndThumbnailFromOembedJson(): void
    {
        $json = json_encode([
            'title' => 'Never Gonna Give You Up',
            'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
        ], JSON_THROW_ON_ERROR);
        $fetcher = new YoutubeOembedTitleFetcher(fn (string $url): string => $json);

        $result = $fetcher->fetch('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        assertSame('Never Gonna Give You Up', $result['title']);
        assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $result['thumbnailUrl']);
    }

    public function testParsesTitleWhenThumbnailUrlIsAbsent(): void
    {
        $json = json_encode(['title' => 'Just A Title'], JSON_THROW_ON_ERROR);
        $fetcher = new YoutubeOembedTitleFetcher(fn (string $url): string => $json);

        $result = $fetcher->fetch('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        assertSame('Just A Title', $result['title']);
        assertSame(null, $result['thumbnailUrl']);
    }

    public function testReturnsNullWhenTitleIsMissing(): void
    {
        $json = json_encode(['thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg'], JSON_THROW_ON_ERROR);
        $fetcher = new YoutubeOembedTitleFetcher(fn (string $url): string => $json);

        assertSame(null, $fetcher->fetch('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function testReturnsNullOnMalformedJson(): void
    {
        $fetcher = new YoutubeOembedTitleFetcher(fn (string $url): string => '{not valid json');

        assertSame(null, $fetcher->fetch('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function testReturnsNullWhenTransportFails(): void
    {
        $fetcher = new YoutubeOembedTitleFetcher(fn (string $url) => false);

        assertSame(null, $fetcher->fetch('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function testReturnsNullWhenTransportReturnsEmptyString(): void
    {
        $fetcher = new YoutubeOembedTitleFetcher(fn (string $url): string => '');

        assertSame(null, $fetcher->fetch('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function testRequestsExpectedOembedUrlWithEncodedTarget(): void
    {
        $requestedUrl = null;
        $fetcher = new YoutubeOembedTitleFetcher(function (string $url) use (&$requestedUrl): string {
            $requestedUrl = $url;
            return json_encode(['title' => 'Title'], JSON_THROW_ON_ERROR);
        });

        $fetcher->fetch('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        assertSame(
            'https://www.youtube.com/oembed?format=json&url=' . rawurlencode('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
            $requestedUrl
        );
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
            );
        }
    }
}
