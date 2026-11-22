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

    /** @return array{ImportTestWorkspace, ImageStore, array<string, mixed>} a public site holding one 900x600 image */
    private function site(): array
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source', true);
        $w->git($root);
        $imageRoot = $w->root . '/images';
        mkdir($imageRoot);
        $source = $w->root . '/picture.png';
        $canvas = imagecreatetruecolor(900, 600);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 240, 235, 225));
        imagefilledellipse($canvas, 450, 300, 400, 300, imagecolorallocate($canvas, 90, 80, 70));
        imagepng($canvas, $source);
        $reference = ImageStore::inspect($source, 'A grey stone');
        $store = new ImageStore($root, $imageRoot);
        $store->put($source, $reference);
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
