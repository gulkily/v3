<?php

declare(strict_types=1);
require __DIR__ . '/../autoload.php';

use ForumRewrite\Http\BandcampPlayerController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\View\BandcampPlayerResolver;
use ForumRewrite\View\BandcampPagePlayerFetcher;
use ForumRewrite\View\TemplateRenderer;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;

final class BandcampPlayerControllerTest
{
    public function testFlagsMethodsAndInvalidUrlsDoNotFetch(): void
    {
        foreach ([[false, true, 'GET', 'https://artist.bandcamp.com/album/release', 403], [true, false, 'GET', 'https://artist.bandcamp.com/album/release', 403], [true, true, 'POST', 'https://artist.bandcamp.com/album/release', 405], [true, true, 'GET', 'https://evil.test/album/release', 400], [true, true, 'GET', ['not-a-string'], 400]] as [$cards, $players, $method, $url, $status]) {
            $calls = 0;
            $this->withController($cards, $players, function ($controller) use ($method, $url, $status) {
                ob_start(); $controller->resolve($method, ['url' => $url]); $body = ob_get_clean();
                assertSame($status, http_response_code());
                assertSame(['status' => 'unavailable'], json_decode($body, true));
            }, function () use (&$calls) { $calls++; return null; });
            assertSame(0, $calls);
        }
    }

    public function testAlbumsResolveWithoutWritingTitlesAndFailuresRemainUsable(): void
    {
        foreach ([true, false] as $success) {
            $calls = 0;
            $this->withController(true, true, function ($controller) use ($success) {
                foreach ([1, 2] as $attempt) {
                    ob_start(); $controller->resolve('GET', ['url' => 'https://lyndseyscott.bandcamp.com/album/well-held', 'thread_id' => 'must-not-write']); $data = json_decode(ob_get_clean(), true);
                    assertSame(200, http_response_code());
                    assertSame($success ? 'ready' : 'unavailable', $data['status']);
                    if ($success) { assertSame(true, str_contains($data['embedSrc'], '/album=1043068150/')); }
                }
            }, function () use (&$calls, $success) {
                $calls++;
                return $success ? ['status' => 200, 'type' => 'text/html', 'body' => '<meta property="og:video" content="https://bandcamp.com/EmbeddedPlayer/album=1043068150/size=large/">'] : null;
            });
            assertSame(1, $calls);
        }
    }

    public function testTrackDiscoverySelectsTrackRatherThanAlbum(): void
    {
        $this->withController(true, true, function ($controller) {
            ob_start(); $controller->resolve('GET', ['url' => 'https://lyndseyscott.bandcamp.com/track/plz-plz-universal-soul']); $data = json_decode(ob_get_clean(), true);
            assertSame('ready', $data['status']);
            assertSame(true, str_contains($data['embedSrc'], '/track=3419983385/'));
            assertSame(false, str_contains($data['embedSrc'], '/album='));
            assertSame(true, str_contains($data['embedSrc'], '/tracklist=false/'));
        }, fn () => ['status' => 200, 'type' => 'text/html', 'body' => '<meta property="og:video" content="https://bandcamp.com/EmbeddedPlayer/v=2/track=3419983385/size=large/">']);
    }

    private function withController(bool $cards, bool $players, callable $test, callable $transport): void
    {
        $root = sys_get_temp_dir() . '/bandcamp-controller-' . bin2hex(random_bytes(8));
        mkdir($root);
        $names = ['FORUM_MEDIA_EMBEDS_ENABLED', 'FORUM_MEDIA_EMBEDS_INLINE_PLAYER_ENABLED'];
        $previous = array_map('getenv', $names);
        foreach ($names as $name) { putenv($name); }
        try {
            $flags = new FeatureFlagEvaluator(siteValues: [FeatureFlagRegistry::MEDIA_EMBEDS_ENABLED => $cards, FeatureFlagRegistry::MEDIA_EMBEDS_INLINE_PLAYER_ENABLED => $players]);
            $services = new RouteServices($root . '/unused.sqlite3', new TemplateRenderer(dirname(__DIR__) . '/templates'), 'php-fallback', false, fn () => null, $root, $root, null, null, $flags, fn () => null);
            $test(new BandcampPlayerController($services, new BandcampPlayerResolver($root, new BandcampPagePlayerFetcher($transport))));
            assertSame(false, file_exists($root . '/unused.sqlite3'));
        } finally {
            foreach ($names as $i => $name) { putenv($previous[$i] === false ? $name : $name . '=' . $previous[$i]); }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
            rmdir($root);
        }
    }
}
