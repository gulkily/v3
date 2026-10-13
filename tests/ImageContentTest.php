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

    public function testBackupInventoriesAndRejectsMissingOrMismatchedPairs(): void
    {
        $base = sys_get_temp_dir() . '/forum-image-backup-' . bin2hex(random_bytes(6));
        mkdir($base . '/repo/records/posts', 0700, true);
        $archives = [];
        try {
            file_put_contents($base . '/pixel.png', self::png());
            $ref = ImageStore::inspect($base . '/pixel.png', 'Pixel');
            $store = new ImageStore($base . '/repo');
            $store->put($base . '/pixel.png', $ref);
            file_put_contents($base . '/repo/records/posts/pixel.txt', "Post-ID: pixel\nCreated-At: 2026-10-13T00:00:00Z\nBoard-Tags: general\n\n" . ImageReference::line($ref) . "\n");
            $git = 'git -C ' . escapeshellarg($base . '/repo');
            foreach (['init -q', 'config user.name Test', 'config user.email test@example.test', 'add .', 'commit -qm Initial'] as $command) {
                exec($git . ' ' . $command . ' 2>&1', $output, $status);
                assertSame(0, $status);
            }
            $backup = new \ForumRewrite\Images\ImageBackup($base . '/repo', $base . '/cache/index.sqlite3');
            $head = $backup->revision();
            $images = $backup->export('images', $head); $archives[] = $images['path'];
            $repo = $backup->export('tar.gz', $head); $archives[] = $repo['path'];
            assertSame($images['revision'], $repo['revision']);
            $tar = new PharData($images['path']);
            $manifest = json_decode($tar['manifest.json']->getContent(), true, 512, JSON_THROW_ON_ERROR);
            assertSame($head, $manifest['repository_head']);
            assertSame(self::png(), $tar['objects/' . ImageReference::filename($ref)]->getContent());
            assertSame(1, count($manifest['objects']));
            $restore = new \ForumRewrite\Images\ImageRestore();
            $result = $restore->run($images['path'], $base . '/repo', $base . '/cache/index.sqlite3', $base . '/restored-images', true);
            assertSame('verified', $result['status']);
            assertSame(false, is_dir($base . '/restored-images'));
            $result = $restore->run($images['path'], $base . '/repo', $base . '/cache/index.sqlite3', $base . '/restored-images');
            assertSame('restored', $result['status']);
            assertSame(self::png(), file_get_contents($base . '/restored-images/' . ImageReference::filename($ref)));
            assertSame($result, $restore->run($images['path'], $base . '/repo', $base . '/cache/index.sqlite3', $base . '/restored-images'));
            $bad = $base . '/unsafe.tar';
            $unsafe = new PharData($bad);
            $unsafe->addFromString('unexpected.txt', 'untrusted'); unset($unsafe);
            $this->reject(fn () => $restore->run($bad, $base . '/repo', $base . '/cache/index.sqlite3', $base . '/restored-images'));
            assertSame(self::png(), file_get_contents($base . '/restored-images/' . ImageReference::filename($ref)));
            exec($git . ' -c user.name=Test -c user.email=test@example.test commit --allow-empty -qm Changed', $output, $status);
            $this->reject(fn () => $restore->run($images['path'], $base . '/repo', $base . '/cache/index.sqlite3', $base . '/restored-images'));
            exec($git . ' reset --hard ' . escapeshellarg($head), $output, $status);

            $this->reject(fn () => $backup->export('images', str_repeat('0', 40)));
            unlink($store->resolve($ref));
            $this->reject(fn () => $backup->export('images', $head));
            $this->reject(fn () => $backup->export('tar.gz', $head));
        } finally {
            foreach ($archives as $path) { @unlink($path); }
            \ForumRewrite\Images\ImageBackup::removeTree($base);
        }
    }

    private function reject(callable $action): void
    {
        try { $action(); } catch (RuntimeException $e) { return; }
        throw new RuntimeException('Expected image operation to be rejected.');
    }
}
