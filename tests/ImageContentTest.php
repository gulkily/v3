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
            $withoutDescription = array_replace($ref, ['description' => '']);
            assertSame($withoutDescription, ImageReference::fromBody(ImageReference::line($withoutDescription)));
            assertStringContains('<img', $renderer->render(ImageReference::line($withoutDescription), false, false, true));
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
            $ref = ImageStore::inspect($base . '/pixel.png', '');
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

    public function testUploadCapacityAndUnsafeArchivesFailWithoutReplacingData(): void
    {
        $base = sys_get_temp_dir() . '/forum-image-limits-' . bin2hex(random_bytes(6));
        mkdir($base . '/images', 0700, true);
        try {
            $source = $base . '/source.png';
            file_put_contents($source, str_repeat('x', ImageReference::MAX_BYTES + 1));
            $this->reject(fn () => ImageStore::inspect($source, 'Too large'));
            file_put_contents($source, self::png());
            $ref = ImageStore::inspect($source, 'Pixel');
            for ($i = 0; $i < ImageStore::MAX_OBJECTS; $i++) {
                $bytes = self::png() . pack('N', $i);
                file_put_contents($base . '/images/' . hash('sha256', $bytes) . '.png', $bytes);
            }
            $store = new ImageStore($base . '/repo', $base . '/images');
            $this->reject(fn () => $store->put($source, $ref));
            assertSame(false, is_file($base . '/images/' . ImageReference::filename($ref)));
            $restore = new \ForumRewrite\Images\ImageRestore();
            foreach (['../escape.png', 'objects/' . str_repeat('a', 64) . '.png'] as $index => $name) {
                // Construct a valid-checksum header with traversal or a symlink type.
                $header = str_repeat("\0", 512);
                $header = substr_replace($header, $name, 0, strlen($name));
                $header = substr_replace($header, "00000000001\0", 124, 12);
                $header[156] = $index === 0 ? '0' : '2';
                $header = substr_replace($header, str_repeat(' ', 8), 148, 8);
                $sum = array_sum(unpack('C*', $header));
                $header = substr_replace($header, sprintf("%06o\0 ", $sum), 148, 8);
                $archive = $base . '/unsafe-' . $index . '.tar';
                file_put_contents($archive, $header . 'x' . str_repeat("\0", 511 + 1024));
                $this->reject(fn () => $restore->run($archive, $base . '/repo', $base . '/cache/db.sqlite3', $base . '/restored'));
                assertSame(false, is_dir($base . '/restored'));
            }
            assertSame(false, is_file($base . '/escape.png'));
        } finally { \ForumRewrite\Images\ImageBackup::removeTree($base); }
    }

    private function reject(callable $action): void
    {
        try { $action(); } catch (RuntimeException $e) { return; }
        throw new RuntimeException('Expected image operation to be rejected.');
    }
}
