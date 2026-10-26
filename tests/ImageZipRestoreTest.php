<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Images\ImageBackup;
use ForumRewrite\Images\ImageReference;
use ForumRewrite\Images\ImageRestore;
use ForumRewrite\Images\ImageStore;

final class ImageZipRestoreTest
{
    public function testZipAndLegacyTarVerifyRestoreAndRetry(): void
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source');
        $w->put($root . '/records/posts/post.txt', $w->post('post'));
        $w->git($root);
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC');
        $w->put($w->root . '/pixel.png', $pixel);
        $ref = ImageStore::inspect($w->root . '/pixel.png', '');
        $store = new ImageStore($root);
        $store->put($w->root . '/pixel.png', $ref);
        $backup = new ImageBackup($root, $w->root . '/index.sqlite3');
        $legacy = $backup->export('images', null, 'tar');
        try {
            $tar = new PharData($legacy['path']);
            $entries = ['manifest.json' => $tar['manifest.json']->getContent(), 'objects/' . ImageReference::filename($ref) => $pixel];
            $zipPath = $w->root . '/images.zip';
            $this->zip($zipPath, $entries, true);
            $code = 'require $argv[1]."/autoload.php"; try { (new ForumRewrite\\Images\\ImageRestore())->run($argv[2],$argv[3],$argv[4],$argv[5],true); } catch (Throwable $e) { fwrite(STDERR,$e->getMessage()); exit(1); }';
            [$missingStatus, $missingOutput] = $w->command(['php', '-n', '-r', $code, dirname(__DIR__), $zipPath, $root,
                $w->root . '/missing.sqlite3', $w->root . '/missing']);
            assertSame(1, $missingStatus);
            assertStringContains('PHP ZIP support is unavailable.', $missingOutput);
            assertSame(false, is_dir($w->root . '/missing'));

            foreach (['zip' => $zipPath, 'tar' => $legacy['path']] as $format => $archive) {
                $target = $w->root . '/restore-' . $format;
                foreach (['verify', 'restore', 'restore'] as $mode) {
                    [$status, $output] = $w->command([dirname(__DIR__) . '/v3', 'images', $mode, $archive, $root,
                        $w->root . '/recovery.sqlite3', '--image-root=' . $target]);
                    assertSame(0, $status);
                    assertStringContains($mode === 'verify' ? 'verified' : 'restored', $output);
                    if ($mode === 'verify') { assertSame(false, is_dir($target)); }
                }
                assertSame($pixel, file_get_contents($target . '/' . ImageReference::filename($ref)));
                // A different existing store must survive a rejected replacement.
                unlink($target . '/' . ImageReference::filename($ref));
                $this->reject($archive, $root, $w, $target);
                assertSame([], glob($target . '/*'));
            }
            $wrong = json_decode($entries['manifest.json'], true, 512, JSON_THROW_ON_ERROR);
            $wrong['repository_head'] = str_repeat('0', 40);
            $this->zip($w->root . '/wrong.zip', array_replace($entries, ['manifest.json' => json_encode($wrong)]));
            $this->reject($w->root . '/wrong.zip', $root, $w);
            assertSame('verified', (new ImageRestore())->run($zipPath, $root, $w->root . '/retry.sqlite3', $w->root . '/retry', true)['status']);
            $this->zip($w->root . '/empty.zip', ['manifest.json' => json_encode(['schema' => 'forum-images-v1', 'repository_head' => $backup->revision(), 'objects' => []])]);
            assertSame(0, (new ImageRestore())->run($w->root . '/empty.zip', $root, $w->root . '/empty.sqlite3', $w->root . '/empty')['objects']);
            assertSame([], glob($w->root . '/.image-restore-*'));
        } finally { unlink($legacy['path']); }
    }

    public function testUnsafeCorruptEncryptedAndOversizedZipEntriesCannotActivateStore(): void
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source'); $w->git($root);
        $manifest = json_encode(['schema' => 'forum-images-v1', 'repository_head' => (new ImageBackup($root, $w->root . '/index.sqlite3'))->revision(), 'objects' => []]);
        $object = 'objects/' . str_repeat('a', 64) . '.png';
        foreach (['../escape', '/absolute', 'objects/../escape', 'objects\\escape', 'unexpected.txt'] as $index => $name) {
            $path = $w->root . '/unsafe-' . $index . '.zip';
            $this->zip($path, ['manifest.json' => $manifest, $name => 'bad']);
            $this->reject($path, $root, $w);
        }
        foreach (['link' => 0120777, 'fifo' => 0010600] as $case => $mode) {
            $path = $w->root . '/' . $case . '.zip';
            $this->zip($path, ['manifest.json' => $manifest, $object => 'bad']);
            $zip = new ZipArchive(); assertSame(true, $zip->open($path));
            assertSame(true, $zip->setExternalAttributesName($object, ZipArchive::OPSYS_UNIX, $mode << 16)); $zip->close();
            $this->reject($path, $root, $w);
        }
        $path = $w->root . '/encrypted.zip';
        $this->zip($path, ['manifest.json' => $manifest]);
        $zip = new ZipArchive(); assertSame(true, $zip->open($path));
        assertSame(true, $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256, 'test-password')); $zip->close();
        $this->reject($path, $root, $w);

        $path = $w->root . '/duplicate.zip';
        $this->zip($path, ['manifest.json' => $manifest, 'manifest.jsox' => $manifest]);
        file_put_contents($path, str_replace('manifest.jsox', 'manifest.json', file_get_contents($path)));
        $this->reject($path, $root, $w);

        $path = $w->root . '/corrupt.zip';
        $this->zip($path, ['manifest.json' => $manifest]);
        $bytes = file_get_contents($path);
        // Stored payload corruption leaves central-directory metadata intact.
        file_put_contents($path, str_replace('forum-images-v1', 'forum-images-v2', $bytes));
        $this->reject($path, $root, $w);
        file_put_contents($path, substr($bytes, 0, -15));
        $this->reject($path, $root, $w);

        foreach (['manifest.json' => 2 * 1024 * 1024 + 1, $object => ImageReference::MAX_BYTES + 1] as $name => $size) {
            $path = $w->root . '/oversized.zip';
            $this->zip($path, ['manifest.json' => $manifest, $name => str_repeat('x', $size)]);
            $this->reject($path, $root, $w);
        }
        $entries = ['manifest.json' => $manifest];
        for ($i = 0; $i < ImageStore::MAX_OBJECTS + 2; $i++) { $entries['objects/' . str_pad(dechex($i), 64, '0', STR_PAD_LEFT) . '.png'] = 'x'; }
        $this->zip($w->root . '/many.zip', $entries);
        $this->reject($w->root . '/many.zip', $root, $w);
        assertSame(false, file_exists($w->root . '/escape'));
    }

    public function testCompressedExpandedTotalIsRejectedBeforeStagingObjects(): void
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source'); $w->git($root);
        $w->put($w->root . '/large-source', str_repeat('x', ImageReference::MAX_BYTES));
        $path = $w->root . '/expanded.zip';
        $zip = new ZipArchive(); assertSame(true, $zip->open($path, ZipArchive::CREATE));
        $zip->addFromString('manifest.json', '{}');
        $count = intdiv(ImageStore::MAX_TOTAL_BYTES + 2 * 1024 * 1024, ImageReference::MAX_BYTES) + 1;
        for ($i = 0; $i < $count; $i++) {
            $zip->addFile($w->root . '/large-source', 'objects/' . str_pad(dechex($i), 64, '0', STR_PAD_LEFT) . '.png');
        }
        assertSame(true, $zip->close());
        assertTrue(filesize($path) < 1024 * 1024);
        try {
            (new ImageRestore())->run($path, $root, $w->root . '/index.sqlite3', $w->root . '/target');
            throw new RuntimeException('Expected expanded capacity rejection.');
        } catch (RuntimeException $error) {
            assertStringContains('exceeds expanded capacity', $error->getMessage());
        }
        assertSame(false, is_dir($w->root . '/target'));
        assertSame([], glob($w->root . '/.image-restore-*'));
    }

    private function zip(string $path, array $entries, bool $directory = false): void
    {
        $zip = new ZipArchive();
        assertSame(true, $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        if ($directory) { $zip->addEmptyDir('objects'); }
        foreach ($entries as $name => $bytes) {
            assertSame(true, $zip->addFromString($name, $bytes));
            $zip->setCompressionName($name, ZipArchive::CM_STORE);
        }
        assertSame(true, $zip->close());
    }

    private function reject(string $archive, string $root, ImportTestWorkspace $w, ?string $target = null): void
    {
        $target ??= $w->root . '/rejected';
        $existed = is_dir($target);
        try {
            (new ImageRestore())->run($archive, $root, $w->root . '/reject.sqlite3', $target);
        } catch (Throwable $error) {
            assertTrue($error instanceof RuntimeException || $error instanceof JsonException);
            assertSame($existed, is_dir($target));
            assertSame([], glob($w->root . '/.image-restore-*'));
            return;
        }
        throw new RuntimeException('Expected archive rejection: ' . $archive);
    }
}
