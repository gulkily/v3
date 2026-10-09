<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\View\InstagramPagePreviewFetcher;

final class InstagramPagePreviewFetcherTest
{
    public function testParsesOgTitleAndOgImageFromPageHtml(): void
    {
        $html = '<html><head>'
            . '<meta property="og:title" content="A great cypher clip">'
            . '<meta property="og:image" content="https://scontent.cdninstagram.com/thumb.jpg">'
            . '</head></html>';
        $fetcher = new InstagramPagePreviewFetcher(fn (string $url): string => $html);

        $result = $fetcher->fetch('https://www.instagram.com/p/Cabc123XYZ/');

        assertSame('A great cypher clip', $result['title']);
        assertSame('https://scontent.cdninstagram.com/thumb.jpg', $result['thumbnailUrl']);
    }

    public function testHandlesContentBeforePropertyAttributeOrder(): void
    {
        $html = '<meta content="Reordered title" property="og:title">'
            . '<meta content="https://example.com/thumb.jpg" property="og:image">';
        $fetcher = new InstagramPagePreviewFetcher(fn (string $url): string => $html);

        $result = $fetcher->fetch('https://www.instagram.com/reel/Cabc123XYZ/');

        assertSame('Reordered title', $result['title']);
        assertSame('https://example.com/thumb.jpg', $result['thumbnailUrl']);
    }

    public function testDecodesHtmlEntitiesInTitle(): void
    {
        $html = '<meta property="og:title" content="Cyphers &amp; freestyles">'
            . '<meta property="og:image" content="https://example.com/thumb.jpg">';
        $fetcher = new InstagramPagePreviewFetcher(fn (string $url): string => $html);

        $result = $fetcher->fetch('https://www.instagram.com/p/Cabc123XYZ/');

        assertSame('Cyphers & freestyles', $result['title']);
    }

    public function testReturnsNullWhenTransportFails(): void
    {
        $fetcher = new InstagramPagePreviewFetcher(fn (string $url) => false);

        assertSame(null, $fetcher->fetch('https://www.instagram.com/p/Cabc123XYZ/'));
    }

    public function testReturnsNullWhenPageHasNoMetaTags(): void
    {
        $fetcher = new InstagramPagePreviewFetcher(fn (string $url): string => '<html><body>Login required</body></html>');

        assertSame(null, $fetcher->fetch('https://www.instagram.com/p/Cabc123XYZ/'));
    }

    public function testReturnsNullWhenOnlyOneOfTheTwoTagsIsPresent(): void
    {
        $html = '<meta property="og:title" content="Only a title">';
        $fetcher = new InstagramPagePreviewFetcher(fn (string $url): string => $html);

        assertSame(null, $fetcher->fetch('https://www.instagram.com/p/Cabc123XYZ/'));
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
