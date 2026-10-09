<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\View\MediaEmbedDetector;

final class MediaEmbedDetectorTest
{
    public function testMatchesKnownYoutubeUrlShapes(): void
    {
        $detector = new MediaEmbedDetector();

        $shapes = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtube.com/watch?v=dQw4w9WgXcQ',
            'http://www.youtube.com/watch?v=dQw4w9WgXcQ&t=30s',
            'https://youtu.be/dQw4w9WgXcQ',
        ];

        foreach ($shapes as $url) {
            $matches = $detector->detect('check this out: ' . $url . ' nice');
            assertSame(1, count($matches));
            assertSame('youtube', $matches[0]['provider']);
            assertSame($url, $matches[0]['url']);
        }
    }

    public function testMatchesKnownInstagramUrlShapes(): void
    {
        $detector = new MediaEmbedDetector();

        $shapes = [
            'https://www.instagram.com/p/Cabc123XYZ/',
            'https://instagram.com/reel/Cabc123XYZ/',
            'https://www.instagram.com/p/Cabc123XYZ',
        ];

        foreach ($shapes as $url) {
            $matches = $detector->detect('look: ' . $url);
            assertSame(1, count($matches));
            assertSame('instagram', $matches[0]['provider']);
            assertSame($url, $matches[0]['url']);
        }
    }

    public function testDoesNotMatchBareDomainOtherProviderScriptSchemeOrTruncatedUrl(): void
    {
        $detector = new MediaEmbedDetector();

        $nonMatches = [
            'just some text with no link at all',
            'https://www.youtube.com',
            'https://vimeo.com/12345678',
            'javascript:alert(1)',
            'https://www.youtube.com/wat',
        ];

        foreach ($nonMatches as $body) {
            assertSame([], $detector->detect($body));
        }
    }

    public function testTrailingSentencePunctuationIsExcludedFromTheMatch(): void
    {
        $detector = new MediaEmbedDetector();

        $matches = $detector->detect('Check this out: https://youtu.be/dQw4w9WgXcQ. Cool right?');

        assertSame(1, count($matches));
        assertSame('https://youtu.be/dQw4w9WgXcQ', $matches[0]['url']);
        assertSame(16, $matches[0]['offset']);
        assertSame(strlen('https://youtu.be/dQw4w9WgXcQ'), $matches[0]['length']);
    }

    public function testDisplayUrlStripsKnownTrackingParamsOnly(): void
    {
        $detector = new MediaEmbedDetector();

        $cases = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ&si=abc123'
                => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s&list=PL123&si=abc'
                => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s&list=PL123',
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ&fbclid=abc'
                => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://www.instagram.com/p/Cabc123XYZ/?igshid=xyz'
                => 'https://www.instagram.com/p/Cabc123XYZ/',
            'https://www.instagram.com/p/Cabc123XYZ/?utm_source=ig&utm_medium=share'
                => 'https://www.instagram.com/p/Cabc123XYZ/',
        ];

        foreach ($cases as $input => $expectedDisplayUrl) {
            $matches = $detector->detect($input);
            assertSame(1, count($matches));
            assertSame($expectedDisplayUrl, $matches[0]['displayUrl']);
        }
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
