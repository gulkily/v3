<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Images\ImageReference;
use ForumRewrite\Images\ImageStore;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;

final class ImageContentTest
{
    public static function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    }

    public function testReferenceAndImmutableStore(): void
    {
        $base = sys_get_temp_dir() . '/forum-image-test-' . bin2hex(random_bytes(6));
        mkdir($base);
        try {
            file_put_contents($base . '/source.png', self::png());
            $ref = ImageStore::inspect($base . '/source.png', 'A white pixel & <caption>');
            assertSame($ref, ImageReference::fromBody("Hello\n" . ImageReference::line($ref)));
            $store = new ImageStore($base . '/repository', $base . '/images');
            $store->put($base . '/source.png', $ref);
            $store->put($base . '/source.png', $ref);
            assertSame(1, count($store->inventory()));
            $renderer = new \ForumRewrite\View\MediaEmbedRenderer(imageStore: $store);
            $body = "Text\n" . ImageReference::line($ref);
            assertStringContains('/images/' . ImageReference::filename($ref), $renderer->render($body, false, false, true));
            assertStringNotContains('<caption>', $renderer->render($body, false, false, true));
            assertStringNotContains('<img', $renderer->render($body, false, false, false));
            assertStringContains('Image unavailable', $renderer->render($body, false, false, false));
            assertSame(self::png(), file_get_contents($store->resolve($ref)));
            $this->reject(fn () => ImageReference::fromBody(ImageReference::line($ref) . "\n" . ImageReference::line($ref)));
            $this->reject(fn () => ImageReference::line(array_replace($ref, ['width' => 4097])));
            $this->reject(fn () => ImageReference::line(array_replace($ref, ['sha256' => '../bad'])));
            $this->reject(fn () => new ImageStore($base . '/repository', $base . '/repository/images'));
            symlink($base . '/images', $base . '/linked');
            $this->reject(fn () => new ImageStore($base . '/repository', $base . '/linked'));
            file_put_contents($store->resolve($ref), 'corrupt');
            $this->reject(fn () => $store->inventory());
        } finally {
            exec('rm -rf ' . escapeshellarg($base));
        }
    }

    public function testFlagIsDefaultOffAndOverridable(): void
    {
        $key = FeatureFlagRegistry::IMAGE_CONTENT_ENABLED;
        $old = getenv($key);
        try {
            putenv($key);
            assertSame(false, (new FeatureFlagEvaluator())->isEnabled($key));
            assertSame(true, (new FeatureFlagEvaluator(siteValues: [$key => true]))->isEnabled($key));
            putenv($key . '=false');
            assertSame(false, (new FeatureFlagEvaluator(siteValues: [$key => true]))->isEnabled($key));
        } finally {
            putenv($old === false ? $key : $key . '=' . $old);
        }
    }

    private function reject(callable $action): void
    {
        try { $action(); } catch (RuntimeException $e) { return; }
        throw new RuntimeException('Expected image operation to be rejected.');
    }
}
