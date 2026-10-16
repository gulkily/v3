<?php

declare(strict_types=1);
require __DIR__ . '/../autoload.php';

use ForumRewrite\View\BandcampReleaseUrl;
use ForumRewrite\View\BandcampPagePlayerFetcher;
use ForumRewrite\View\BandcampPlayerResolver;

final class BandcampPlayerResolverTest
{
    public function testPrivateReservedAndSharedNetworkDestinationsAreRejected(): void
    {
        $fetcher = new BandcampPagePlayerFetcher();
        assertSame(true, $fetcher->isPublicAddress('151.101.1.91'));
        foreach (['127.0.0.1', '10.0.0.1', '169.254.169.254', '192.168.1.1', '100.64.0.1', '192.0.2.1', '198.18.0.1', '203.0.113.1', '224.0.0.1', '::1', 'not-an-ip'] as $ip) {
            assertSame(false, $fetcher->isPublicAddress($ip));
        }
    }

    public function testOnlyDirectPublicArtistReleaseUrlsAreAccepted(): void
    {
        assertSame(['url' => 'https://lyndseyscott.bandcamp.com/album/well-held', 'kind' => 'album'], BandcampReleaseUrl::parse('http://LyndseyScott.bandcamp.com/album/well-held/?utm_source=x#section'));
        foreach (['https://bandcamp.com/album/x', 'https://help.bandcamp.com/track/x', 'https://a.b.bandcamp.com/album/x', 'https://a.bandcamp.com.evil.test/album/x', 'https://a.bandcamp.com:443/album/x', 'https://a.bandcamp.com/album/x/extra', 'https://a.bandcamp.com/track/x?token=private', 'https://a.bandcamp.com/track/x#access_token=x', 'https://a.bandcamp.com/track/%78', 'https://a.bandcamp.com/album/../x', 'https://u@a.bandcamp.com/album/x'] as $url) {
            assertSame(null, BandcampReleaseUrl::parse($url));
        }
    }

    public function testMetadataIsParsedAsIdentifiersNotTrustedHtml(): void
    {
        foreach (['album' => '1043068150', 'track' => '3419983385'] as $kind => $id) {
            $requested = '';
            $fetcher = new BandcampPagePlayerFetcher(function ($url) use ($kind, $id, &$requested) {
                $requested = $url;
                return $this->response('<script>evil()</script><meta content="https://bandcamp.com/EmbeddedPlayer/v=2/' . $kind . '=' . $id . '/size=large/tracklist=false/artwork=small/" property="og:video">');
            });
            assertSame(['kind' => $kind, 'id' => $id], $fetcher->fetch('https://lyndseyscott.bandcamp.com/' . $kind . '/release/?tag=kept'));
            assertSame('https://lyndseyscott.bandcamp.com/' . $kind . '/release', $requested);
        }
        $called = false;
        $fetcher = new BandcampPagePlayerFetcher(function () use (&$called) { $called = true; return null; });
        assertSame(null, $fetcher->fetch('https://evil.test/album/x'));
        assertSame(false, $called);
    }

    public function testFailuresAndAmbiguousOrForeignPlayerMetadataAreRejected(): void
    {
        $valid = '<meta property="og:video" content="https://bandcamp.com/EmbeddedPlayer/album=1043068150/size=large/">';
        foreach ([
            $this->response('<title>Client Challenge</title>'),
            $this->response($valid, 302), $this->response($valid, 404),
            ['status' => 200, 'type' => 'application/json', 'body' => $valid],
            $this->response(str_repeat(' ', 1048577) . $valid),
            $this->response(str_replace('bandcamp.com/', 'bandcamp.com.evil.test/', $valid)),
            $this->response(str_replace('album=', 'track=', $valid)),
            $this->response(str_replace('1043068150', '0', $valid)),
            $this->response($valid . str_replace('1043068150', '123', $valid)),
            $this->response(str_replace('/size=large/', '/size=large/?redirect=https://evil.test', $valid)),
            null,
        ] as $response) {
            $fetcher = new BandcampPagePlayerFetcher(fn () => $response);
            assertSame(null, $fetcher->fetch('https://artist.bandcamp.com/album/release'));
        }
        assertSame(null, (new BandcampPagePlayerFetcher(function () { throw new RuntimeException('timeout'); }))->fetch('https://artist.bandcamp.com/album/release'));
    }

    public function testCacheExpiryNegativeBackoffAndConcurrentDeduplication(): void
    {
        $root = sys_get_temp_dir() . '/bandcamp-resolver-' . bin2hex(random_bytes(8));
        $calls = 0;
        $result = null;
        $fetcher = new BandcampPagePlayerFetcher(function () use (&$calls, &$result) { $calls++; return $result; });
        $resolver = new BandcampPlayerResolver($root, $fetcher);
        $url = 'https://artist.bandcamp.com/album/release';
        $file = $root . '/state/cache/bandcamp_players/' . hash('sha256', $url) . '.json';
        try {
            assertSame(null, $resolver->resolve($url));
            assertSame(null, $resolver->resolve($url . '?utm_source=x'));
            assertSame(1, $calls);
            $cached = json_decode(file_get_contents($file), true);
            assertSame(true, abs($cached['expires'] - time() - 3600) < 3);
            file_put_contents($file, '{"expires":0,"result":null}');
            $result = $this->response('<meta property="og:video" content="https://bandcamp.com/EmbeddedPlayer/album=1043068150/size=large/">');
            $player = $resolver->resolve($url);
            assertSame(true, str_contains($player, '/album=1043068150/'));
            assertSame($player, $resolver->resolve($url));
            assertSame(2, $calls);
            $cached = json_decode(file_get_contents($file), true);
            assertSame(true, abs($cached['expires'] - time() - 86400) < 3);
            $lock = fopen($file, 'c+'); flock($lock, LOCK_EX);
            assertSame(null, $resolver->resolve($url));
            assertSame(2, $calls);
            fclose($lock);
            file_put_contents($file, 'broken json');
            assertSame($player, $resolver->resolve($url));
            assertSame(3, $calls);
        } finally {
            @unlink($file); @rmdir(dirname($file)); @rmdir($root . '/state/cache'); @rmdir($root . '/state'); @rmdir($root);
        }
    }

    private function response(string $body, int $status = 200): array
    {
        return ['status' => $status, 'type' => 'text/html; charset=UTF-8', 'body' => $body];
    }
}
