<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';
require_once __DIR__ . '/Support/ImportHttpServer.php';

use ForumRewrite\Images\ImageStore;
use ForumRewrite\Images\ThumbnailStore;

/** How images are answered over HTTP: what a browser may keep, and what it may not. */
final class ImageServingTest
{
    public function testAServedImageMayBeKeptForAYearAndAnErrorNever(): void
    {
        [$w, $store, $reference] = $this->site();
        $server = $this->server($w, ['FORUM_IMAGE_CONTENT_ENABLED' => 'true']);
        $name = \ForumRewrite\Images\ImageReference::filename($reference);

        [$status, $headers, $body] = $this->request($server->url . '/images/' . $name);
        assertSame(200, $status);
        assertSame(file_get_contents($store->root . '/' . $name), $body);
        assertStringContains('Cache-Control: private, max-age=31536000, immutable', $headers);
        assertStringNotContains('no-store', $headers);
        assertStringContains('X-Content-Type-Options: nosniff', $headers);

        if (ThumbnailStore::available()) {
            [$status, $headers] = $this->request($server->url . '/images/thumbs/' . ThumbnailStore::filename($reference, 'tile'));
            assertSame(200, $status);
            assertStringContains('Cache-Control: private, max-age=31536000, immutable', $headers);
            assertStringNotContains('no-store', $headers);
        }

        foreach (['/images/' . str_repeat('0', 64) . '.png', '/images/thumbs/' . str_repeat('0', 64) . '-tile.png.webp', '/images/nonsense'] as $missing) {
            [$status, $headers] = $this->request($server->url . $missing);
            assertSame(404, $status, $missing);
            assertStringContains('Cache-Control: private, no-store', $headers, $missing);
            assertStringNotContains('max-age=31536000', $headers, $missing);
        }
        unset($server);

        $off = $this->server($w, ['FORUM_IMAGE_CONTENT_ENABLED' => 'false']);
        [$status, $headers] = $this->request($off->url . '/images/' . $name);
        assertSame(404, $status);
        assertStringContains('no-store', $headers);
    }

    public function testPagesShowCompressedCopiesAndThreadsLinkTheOriginal(): void
    {
        if (!ThumbnailStore::available() || ThumbnailStore::format() !== 'webp') {
            return; // Without WebP the original is the fallback, as before.
        }
        [$w, $store, $reference] = $this->site();
        $name = \ForumRewrite\Images\ImageReference::filename($reference);
        $original = '/images/' . $name;

        // 900x600: a 480px tile, then one display copy at the original's own width; `large` would repeat it.
        $sources = ThumbnailStore::displaySources($reference);
        assertSame([480, 900], array_column($sources, 'width'));
        assertSame('/images/thumbs/' . ThumbnailStore::filename($reference, 'tile'), $sources[0]['url']);
        assertSame('/images/thumbs/' . ThumbnailStore::filename($reference, 'wide'), $sources[1]['url']);
        assertSame($sources[0]['url'] . ' 480w, ' . $sources[1]['url'] . ' 900w', ThumbnailStore::displaySrcset($reference));
        // A larger original gets all three, and none is wider than the original.
        $big = ['width' => 2000, 'height' => 1000] + $reference;
        assertSame([480, 960, 1600], array_column(ThumbnailStore::displaySources($big), 'width'));
        // An original no wider than the tile is its own only entry.
        $tiny = ['width' => 40, 'height' => 40] + $reference;
        assertSame(1, count(ThumbnailStore::displaySources($tiny)));
        assertSame(null, ThumbnailStore::displaySrcset($tiny));

        $server = $this->server($w, ['FORUM_IMAGE_CONTENT_ENABLED' => 'true', 'FORUM_LIST_THUMBNAILS_ENABLED' => 'true', 'FORUM_IMAGE_GALLERY_ENABLED' => 'true']);
        [$status, , $thread] = $this->request($server->url . '/threads/picture-thread');
        assertSame(200, $status);
        assertStringContains('<a class="post-image__original" href="' . $original . '"><img src="' . $sources[1]['url'] . '" srcset="' . ThumbnailStore::displaySrcset($reference) . '" sizes="(min-width: 48rem) 45rem, 100vw" alt="A grey stone" width="900" height="600"', $thread);

        // No page puts the stored original inside an image tag.
        foreach (['/threads/picture-thread', '/threads/', '/recent/', '/gallery/'] as $page) {
            [$status, , $html] = $this->request($server->url . $page);
            assertSame(200, $status, $page);
            assertStringContains('/images/thumbs/' . $reference['sha256'], $html, $page);
            assertSame(0, preg_match('#<img[^>]*["\' ,]' . preg_quote($original, '#') . '["\' ,]#', $html), $page);
        }

        // The display copy is a real WebP, far smaller than the PNG it stands in for.
        [$status, $headers, $copy] = $this->request($server->url . $sources[1]['url']);
        assertSame(200, $status);
        assertStringContains('Content-Type: image/webp', $headers);
        assertSame([900, 600], array_slice(getimagesizefromstring($copy), 0, 2));
        assertTrue(strlen($copy) * 2 < $reference['bytes'], strlen($copy) . ' vs ' . $reference['bytes']);
        [$status, , $body] = $this->request($server->url . $original);
        assertSame($reference['bytes'], strlen($body));
    }

    public function testACopyNoSmallerThanItsOriginalIsAnsweredWithTheOriginal(): void
    {
        if (!ThumbnailStore::available() || ThumbnailStore::format() !== 'webp') {
            return;
        }
        [$w, $store] = $this->site();
        // A flat two-colour PNG is a few hundred bytes; no lossy copy can beat it.
        $flat = $w->root . '/flat.png';
        $canvas = imagecreate(600, 400);
        imagecolorallocate($canvas, 240, 235, 225);
        imagefilledrectangle($canvas, 100, 100, 500, 300, imagecolorallocate($canvas, 40, 40, 40));
        imagepng($canvas, $flat, 9);
        $reference = ImageStore::inspect($flat, 'A flat card');
        $store->put($flat, $reference);

        $server = $this->server($w, ['FORUM_IMAGE_CONTENT_ENABLED' => 'true']);
        [$status, $headers, $body] = $this->request($server->url . '/images/thumbs/' . ThumbnailStore::filename($reference, 'wide'));
        assertSame(200, $status);
        assertStringContains('Content-Type: image/png', $headers);
        assertSame(file_get_contents($flat), $body);
        assertStringContains('max-age=31536000', $headers);
    }

    /** @return array{ImportTestWorkspace, ImageStore, array<string, mixed>} a public site holding one 900x600 image */
    private function site(): array
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source', true);
        $imageRoot = $w->root . '/images';
        mkdir($imageRoot);
        $source = $w->root . '/picture.png';
        $canvas = imagecreatetruecolor(900, 600);
        // Photo-like: smooth gradients with a little grain, which PNG stores badly and WebP well.
        mt_srand(7);
        for ($y = 0; $y < 600; $y++) {
            for ($x = 0; $x < 900; $x++) {
                $g = mt_rand(-6, 6);
                imagesetpixel($canvas, $x, $y, (max(0, min(255, intdiv($x * 255, 900) + $g)) << 16) | (max(0, min(255, intdiv($y * 255, 600) + $g)) << 8) | max(0, min(255, 128 + $g)));
            }
        }
        imagepng($canvas, $source);
        $reference = ImageStore::inspect($source, 'A grey stone');
        $store = new ImageStore($root, $imageRoot);
        $store->put($source, $reference);
        $w->put($root . '/records/posts/picture-thread.txt', "Post-ID: picture-thread\nCreated-At: 2026-07-01T00:00:00Z\nBoard-Tags: general\nSubject: A picture\n\nLook at this.\n" . \ForumRewrite\Images\ImageReference::line($reference) . "\n");
        $w->git($root);
        return [$w, $store, $reference];
    }

    /** @param array<string, string> $extra */
    private function server(ImportTestWorkspace $w, array $extra): ImportHttpServer
    {
        static $n = 0;
        $n++;
        return new ImportHttpServer(realpath(__DIR__ . '/../public/router.php'), $w->root . '/server' . $n . '.log', $extra + [
            'FORUM_REPOSITORY_ROOT' => $w->root . '/source', 'FORUM_DATABASE_PATH' => $w->root . '/cache/index.sqlite3',
            'FORUM_STATIC_HTML_ROOT' => $w->root . '/static', 'FORUM_IMAGE_ROOT' => $w->root . '/images',
            'FORUM_TASK_QUEUE_DATABASE_PATH' => $w->root . '/queue.sqlite3', 'FORUM_APPROVED_MEMBERS_ONLY' => 'false',
        ]);
    }

    /** @return array{int, string, string} */
    private function request(string $url): array
    {
        $body = file_get_contents($url, false, stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0]]));
        preg_match('#^HTTP/\S+ (\d+)#', $http_response_header[0], $match);
        return [(int) $match[1], implode("\n", $http_response_header), (string) $body];
    }
}
