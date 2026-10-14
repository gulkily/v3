<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\View\MediaEmbedDetector;

final class MediaEmbedDetectorTest
{
    public function testShortsUseTheYoutubeProviderAndPreserveSourceParameters(): void
    {
        $detector = new MediaEmbedDetector();
        foreach (['https://www.youtube.com', 'http://youtube.com', 'https://YOUTUBE.COM'] as $base) {
            $url = $base . '/shorts/M7lc1UVf-VE/?t=4&tag=a%20b&tag=c&si=tracking#section';
            $match = $detector->detect('Clip: ' . $url . '.')[0];
            assertSame('youtube', $match['provider']);
            assertSame('M7lc1UVf-VE', $match['embedId']);
            assertSame('shorts', $match['contentKind']);
            assertSame($url, $match['url']);
            assertSame($base . '/shorts/M7lc1UVf-VE/?t=4&tag=a%20b&tag=c#section', $match['displayUrl']);
        }
    }

    public function testRejectsLookalikeHostsIncompleteIdentifiersAndUnsupportedYoutubeForms(): void
    {
        $detector = new MediaEmbedDetector();
        foreach ([
            'https://youtube.com.evil.test/watch?v=M7lc1UVf-VE',
            'https://youtube.com@evil.test/watch?v=M7lc1UVf-VE',
            'https://user@youtube.com/watch?v=M7lc1UVf-VE',
            'https://youtu.be/M7lc1UVf-V',
            'https://youtu.be/M7lc1UVf-VEextra',
            'https://youtu.be/M7lc1UVf-VE/extra',
            'https://youtube.com/watch?v=M7lc1UVf-VEextra',
            'https://youtube.com/watch?v[]=M7lc1UVf-VE',
            'https://youtube.com/shorts/M7lc1UVf-VE/extra',
            'https://youtube.com/live/M7lc1UVf-VE',
            'https://youtube.com/embed/M7lc1UVf-VE',
            'https://m.youtube.com/watch?v=M7lc1UVf-VE',
            'https://youtube.com/playlist?list=PL123',
            'https://instagram.com.evil.test/p/Cabc123XYZ/',
            'https://instagram.com/p/Cabc123XYZ/extra',
            'https://instagram.com/p/Cabc123XYZ.invalid/',
        ] as $url) {
            assertSame(null, $detector->classify($url));
        }
    }

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
            assertSame('dQw4w9WgXcQ', $matches[0]['embedId']);
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
            assertSame('Cabc123XYZ', $matches[0]['embedId']);
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

    public function testClassifyValidatesAndExtractsFromAStandaloneUrl(): void
    {
        $detector = new MediaEmbedDetector();

        assertSame(
            ['provider' => 'instagram', 'embedId' => 'Cabc123XYZ'],
            $detector->classify('https://www.instagram.com/p/Cabc123XYZ/')
        );
        assertSame(
            ['provider' => 'youtube', 'embedId' => 'dQw4w9WgXcQ'],
            $detector->classify('https://youtu.be/dQw4w9WgXcQ')
        );
        assertSame(null, $detector->classify('https://vimeo.com/12345678'));
        assertSame(null, $detector->classify('javascript:alert(1)'));
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
