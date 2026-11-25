<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Http\InstagramClipImageController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\View\InstagramPagePreviewFetcher;
use ForumRewrite\View\MediaEmbedDetector;
use ForumRewrite\View\TemplateRenderer;

final class InstagramClipImageControllerTest
{
    private const JPEG = "\xFF\xD8\xFF\xE0fakejpeg";

    public function testGivenInstagramImageIsFetchedAndReturned(): void
    {
        $requested = [];
        $page = new InstagramPagePreviewFetcher(function (): string { throw new RuntimeException('page should not be fetched'); });
        $out = $this->run(['post' => 'https://www.instagram.com/p/Cabc123/', 'image' => 'https://scontent-bos5-1.cdninstagram.com/v/t51/x.jpg?oe=1'], $page, function (string $url) use (&$requested): string {
            $requested[] = $url;
            return self::JPEG;
        });
        assertSame(200, $out['status']);
        assertSame(self::JPEG, $out['body']);
        assertSame(['https://scontent-bos5-1.cdninstagram.com/v/t51/x.jpg?oe=1'], $requested);
    }

    public function testOtherImageHostsAreIgnoredForThePostPageImage(): void
    {
        $requested = [];
        $page = new InstagramPagePreviewFetcher(fn (string $url): string => '<meta property="og:title" content="t"><meta property="og:image" content="https://instagram.fxyz1-1.fna.fbcdn.net/og.jpg">');
        $out = $this->run(['post' => 'https://www.instagram.com/reel/Cabc123/', 'image' => 'https://evil.example/x.jpg'], $page, function (string $url) use (&$requested): string {
            $requested[] = $url;
            return self::JPEG;
        });
        assertSame(200, $out['status']);
        assertSame(['https://instagram.fxyz1-1.fna.fbcdn.net/og.jpg'], $requested);
    }

    public function testNonImageBytesAndBadPostsAreRefused(): void
    {
        $page = new InstagramPagePreviewFetcher(fn (string $url): string => '<meta property="og:title" content="t"><meta property="og:image" content="https://evil.example/og.jpg">');
        $out = $this->run(['post' => 'https://www.instagram.com/p/Cabc123/', 'image' => 'https://scontent.cdninstagram.com/x.jpg'], $page, fn (): string => '<html>not an image</html>');
        assertSame(502, $out['status']);

        $out = $this->run(['post' => 'https://example.com/p/Cabc123/'], $page, fn (): string => self::JPEG);
        assertSame(400, $out['status']);
    }

    public function testInstagramImageHostCheck(): void
    {
        assertTrue(InstagramClipImageController::isInstagramImageUrl('https://scontent.cdninstagram.com/a.jpg'));
        assertTrue(InstagramClipImageController::isInstagramImageUrl('https://instagram.fbos1-2.fna.fbcdn.net/a.jpg'));
        assertFalse(InstagramClipImageController::isInstagramImageUrl('http://scontent.cdninstagram.com/a.jpg'));
        assertFalse(InstagramClipImageController::isInstagramImageUrl('https://cdninstagram.com.evil.example/a.jpg'));
        assertFalse(InstagramClipImageController::isInstagramImageUrl('https://x@scontent.cdninstagram.com/a.jpg'));
        assertFalse(InstagramClipImageController::isInstagramImageUrl('https://scontent.cdninstagram.com:8443/a.jpg'));
        assertSame('webp', InstagramClipImageController::imageType("RIFF\0\0\0\0WEBPVP8 "));
        assertSame(null, InstagramClipImageController::imageType('<svg'));
    }

    /** @return array{status: int, body: string} */
    private function run(array $query, InstagramPagePreviewFetcher $page, callable $transport): array
    {
        $root = sys_get_temp_dir() . '/ig-clip-' . bin2hex(random_bytes(4));
        mkdir($root);
        try {
            $routeServices = new RouteServices(
                $root . '/read_model.sqlite3',
                new TemplateRenderer(dirname(__DIR__) . '/templates'),
                'php-fallback',
                false,
                static fn (): ?array => null,
                $root,
                $root,
                null,
                null,
                FeatureFlagEvaluator::forApplication($root, $root),
                static fn (): ?array => null,
            );
            http_response_code(200);
            ob_start();
            (new InstagramClipImageController($routeServices, new MediaEmbedDetector(), $page, $transport))->image('GET', $query);
            $body = (string) ob_get_clean();

            return ['status' => (int) http_response_code(), 'body' => $body];
        } finally {
            @rmdir($root);
        }
    }
}
