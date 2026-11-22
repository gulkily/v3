<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';
require_once __DIR__ . '/Support/ImportHttpServer.php';

use ForumRewrite\Images\ImageStore;
use ForumRewrite\Images\PublishedImages;
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

    public function testAMembersOnlyBoardPublishesNothingAndLockingEmptiesTheFolder(): void
    {
        [$w, , $reference] = $this->site();
        $name = \ForumRewrite\Images\ImageReference::filename($reference);
        $published = new PublishedImages($w->root . '/published');
        $on = ['FORUM_IMAGE_CONTENT_ENABLED' => 'true', 'FORUM_IMAGE_DIRECT_SERVING_ENABLED' => 'true', 'FORUM_PUBLISHED_IMAGE_ROOT' => $published->root];

        // Members-only with the flag on: the image is refused and nothing is published.
        $locked = $this->server($w, ['FORUM_APPROVED_MEMBERS_ONLY' => 'true'] + $on);
        [$status] = $this->request($locked->url . '/images/' . $name);
        assertTrue($status !== 200, 'status ' . $status);
        assertSame(false, is_dir($published->root));

        // Copies published while the board was public are gone after the locked board's next request of any kind.
        $published->publish($name, $w->root . '/images/' . $name);
        $published->publish('thumbs/' . ThumbnailStore::filename($reference, 'tile'), $w->root . '/images/' . $name);
        assertSame(2, count($published->names()));
        $this->request($locked->url . '/');
        assertSame([], $published->names());
        assertSame(false, is_dir($published->root));
        unset($locked);

        // The same when the board stays public and the flag goes off, or image attachments go off.
        foreach ([['FORUM_IMAGE_DIRECT_SERVING_ENABLED' => 'false'], ['FORUM_IMAGE_CONTENT_ENABLED' => 'false']] as $change) {
            $published->publish($name, $w->root . '/images/' . $name);
            assertSame([$name], $published->names());
            $server = $this->server($w, $change + $on);
            $this->request($server->url . '/api/version');
            assertSame(false, is_dir($published->root));
            unset($server);
        }

        // With the flag off on a public board, serving an image publishes nothing.
        $off = $this->server($w, ['FORUM_IMAGE_DIRECT_SERVING_ENABLED' => 'false'] + $on);
        [$status] = $this->request($off->url . '/images/' . $name);
        assertSame(200, $status);
        assertSame(false, is_dir($published->root));
    }

    public function testAPublicBoardPublishesWhatItServesAndRemovalTakesItAway(): void
    {
        [$w, $store, $reference] = $this->site();
        $name = \ForumRewrite\Images\ImageReference::filename($reference);
        $published = new PublishedImages($w->root . '/published');
        $server = $this->server($w, ['FORUM_IMAGE_CONTENT_ENABLED' => 'true', 'FORUM_IMAGE_DIRECT_SERVING_ENABLED' => 'true', 'FORUM_PUBLISHED_IMAGE_ROOT' => $published->root]);

        // Nothing is published until it has been served.
        $this->request($server->url . '/threads/picture-thread');
        assertSame([], $published->names());

        [$status, , $body] = $this->request($server->url . '/images/' . $name);
        assertSame(200, $status);
        assertSame([$name], $published->names());
        assertSame($body, file_get_contents($published->root . '/' . $name));
        assertSame('0644', substr(sprintf('%o', fileperms($published->root . '/' . $name)), -4));
        $rules = (string) file_get_contents($published->root . '/.htaccess');
        assertStringContains('Header set Cache-Control "private, max-age=31536000, immutable"', $rules);
        assertStringContains('Options -Indexes', $rules);

        $expected = [$name];
        if (ThumbnailStore::available() && ThumbnailStore::format() === 'webp') {
            $wide = ThumbnailStore::filename($reference, 'wide');
            [$status, , $copy] = $this->request($server->url . '/images/thumbs/' . $wide);
            assertSame(200, $status);
            assertSame($copy, file_get_contents($published->root . '/thumbs/' . $wide));
            $expected[] = 'thumbs/' . $wide;

            // A legacy name answered in the current format is not published under the legacy name.
            [$status] = $this->request($server->url . '/images/thumbs/' . $reference['sha256'] . '-tile.png');
            assertSame(200, $status);
            // A copy answered with the original (no smaller) is not published under the copy's name.
            $flat = $w->root . '/flat.png';
            $canvas = imagecreate(600, 400);
            imagecolorallocate($canvas, 240, 235, 225);
            imagepng($canvas, $flat, 9);
            $flatReference = ImageStore::inspect($flat, 'Flat');
            $store->put($flat, $flatReference);
            [$status] = $this->request($server->url . '/images/thumbs/' . ThumbnailStore::filename($flatReference, 'wide'));
            assertSame(200, $status);
            $expected[] = \ForumRewrite\Images\ImageReference::filename($flatReference);
        }
        sort($expected);
        assertSame($expected, $published->names());

        // A missing image and a made-up name publish nothing.
        $this->request($server->url . '/images/' . str_repeat('0', 64) . '.png');
        $published->publish('../escape.png', $w->root . '/images/' . $name);
        $published->publish('thumbs/../../escape.png', $w->root . '/images/' . $name);
        assertSame($expected, $published->names());
        assertSame(false, file_exists($w->root . '/escape.png'));

        // Removing the image removes its published original and copies; other images stay.
        $old = getenv('FORUM_PUBLISHED_IMAGE_ROOT');
        putenv('FORUM_PUBLISHED_IMAGE_ROOT=' . $published->root);
        try {
            assertSame(true, $store->remove($reference));
        } finally {
            putenv($old === false ? 'FORUM_PUBLISHED_IMAGE_ROOT' : 'FORUM_PUBLISHED_IMAGE_ROOT=' . $old);
        }
        assertSame(array_values(array_filter($expected, static fn (string $n): bool => !str_contains($n, $reference['sha256']))), $published->names());
        [$status, $headers] = $this->request($server->url . '/images/' . $name);
        assertSame(404, $status);
        assertStringContains('no-store', $headers);
    }

    public function testTheApacheRuleMatchesExactlyThePublishedNames(): void
    {
        $htaccess = (string) file_get_contents(__DIR__ . '/../public/.htaccess');
        assertSame(1, preg_match('/^RewriteRule \^images\/(\S+)\$ \.published-images\/\$1 \[END\]$/m', $htaccess, $rule));
        $pattern = '#^images/' . $rule[1] . '$#';
        $sha = str_repeat('a', 64);
        foreach (["images/$sha.png", "images/$sha.jpg", "images/thumbs/$sha-tile.png.webp", "images/thumbs/$sha-wide.jpg.webp", "images/thumbs/$sha-sq2x.png"] as $served) {
            assertSame(1, preg_match($pattern, $served), $served);
        }
        foreach (["images/.htaccess", "images/../index.php", "images/thumbs/../$sha.png", "images/$sha.png/x", "images/$sha.php", "images/thumbs/$sha-dot.png.webp.php", "images/x/$sha.png"] as $refused) {
            assertSame(0, preg_match($pattern, $refused), $refused);
        }
        // The folder is not an address, and the rule only fires for a file that exists.
        assertStringContains('RewriteRule ^\.published-images(?:/|$) - [F,NC,L]', $htaccess);
        assertStringContains('RewriteCond %{DOCUMENT_ROOT}/.published-images/$1 -f', $htaccess);
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
