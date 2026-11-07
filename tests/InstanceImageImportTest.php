<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Images\ImageReference;
use ForumRewrite\Images\ImageStore;
use ForumRewrite\Import\InstanceImageImport;

final class InstanceImageImportTest
{
    /** A 1-pixel PNG with its IHDR width patched, so each width gives a distinct valid image. */
    private static function png(int $width): string
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC');
        return substr_replace($png, pack('N', $width), 16, 4);
    }

    private static function zip(string $path, array $entries): void
    {
        $zip = new ZipArchive();
        assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) === true);
        $zip->addFromString('manifest.json', '{"schema":"forum-images-v1","repository_head":"other-revision","objects":{}}');
        $zip->addEmptyDir('objects');
        foreach ($entries as $name => $bytes) { $zip->addFromString($name, $bytes); }
        assertTrue($zip->close());
    }

    private static function reference(ImportTestWorkspace $w, string $bytes, string $description): array
    {
        $path = $w->root . '/ref-' . bin2hex(random_bytes(4)) . '.png';
        $w->put($path, $bytes);
        return ImageStore::inspect($path, $description);
    }

    public function testCopiesOnlyMissingReferencedImagesIntoANonEmptyStore(): void
    {
        $w = new ImportTestWorkspace();
        $repository = $w->repository('destination');
        $existingBytes = self::png(1);
        $existing = self::reference($w, $existingBytes, 'Existing');
        $wanted = self::reference($w, self::png(2), 'Imported');
        $removed = self::reference($w, self::png(3), 'Removed at source');
        $absent = self::reference($w, self::png(4), 'Never backed up');
        $w->put($repository . '/records/posts/existing.txt', $w->post('existing', '', ImageReference::line($existing)));
        $w->put($repository . '/records/posts/imported.txt', $w->post('imported', '', ImageReference::line($wanted)));
        $w->put($repository . '/records/posts/absent.txt', $w->post('absent', '', ImageReference::line($absent)));
        $store = new ImageStore($repository);
        $w->put($w->root . '/existing.png', $existingBytes);
        $store->put($w->root . '/existing.png', $existing);
        $before = hash_file('sha256', $store->root . '/' . ImageReference::filename($existing));

        $zip = $w->root . '/images.zip';
        self::zip($zip, [
            'objects/' . ImageReference::filename($existing) => self::png(9),
            'objects/' . ImageReference::filename($wanted) => self::png(2),
            'objects/' . ImageReference::filename($removed) => self::png(3),
        ]);
        $import = new InstanceImageImport($repository, $w->root . '/cache/index.sqlite3');
        mkdir($w->root . '/cache', 0700);
        $references = $import->references();
        assertSame(3, count($references));

        $preview = $import->run($zip, $references, true);
        assertSame(['added' => 1, 'present' => 1, 'missing' => 1, 'reasons' => ['not in the source image backup' => 1]], $preview);
        assertSame(false, $store->isPresent($wanted));

        $result = $import->run($zip, $references);
        assertSame(['added' => 1, 'present' => 1, 'missing' => 1, 'reasons' => ['not in the source image backup' => 1]], $result);
        assertSame($wanted, ImageStore::inspect($store->resolve($wanted), 'Imported'));
        assertSame(false, is_file($store->root . '/' . ImageReference::filename($removed)));
        assertSame($before, hash_file('sha256', $store->root . '/' . ImageReference::filename($existing)));

        $repeat = $import->run($zip, $references);
        assertSame(['added' => 0, 'present' => 2, 'missing' => 1, 'reasons' => ['not in the source image backup' => 1]], $repeat);
    }

    public function testMismatchedAndOverCapacityImagesAreReportedMissing(): void
    {
        $w = new ImportTestWorkspace();
        $repository = $w->repository('destination');
        mkdir($w->root . '/cache', 0700);
        $good = self::reference($w, self::png(5), 'Good');
        $bad = self::reference($w, self::png(6), 'Bad');
        $zip = $w->root . '/images.zip';
        self::zip($zip, [
            'objects/' . ImageReference::filename($good) => self::png(5),
            'objects/' . ImageReference::filename($bad) => self::png(7),
        ]);
        $import = new InstanceImageImport($repository, $w->root . '/cache/index.sqlite3');
        $references = [ImageReference::filename($good) => $good, ImageReference::filename($bad) => $bad];
        $result = $import->run($zip, $references);
        assertSame(['added' => 1, 'present' => 0, 'missing' => 1, 'reasons' => ['source image does not match its post' => 1]], $result);

        $full = new ImageStore($repository . '-full-store-check', $w->root . '/full-store');
        $items = [];
        for ($i = 0; $i <= ImageStore::MAX_OBJECTS; $i++) {
            $bytes = self::png(100 + $i);
            $path = $w->root . '/many/' . $i . '.png';
            $w->put($path, $bytes);
            $items[] = ['source' => $path, 'reference' => ImageStore::inspect($path, '')];
        }
        $outcome = $full->putAll($items);
        assertSame(ImageStore::MAX_OBJECTS, count($outcome['added']));
        assertSame(1, count($outcome['full']));
    }

    public function testCorruptZipFailsWithoutChangingTheStore(): void
    {
        $w = new ImportTestWorkspace();
        $repository = $w->repository('destination');
        mkdir($w->root . '/cache', 0700);
        $ref = self::reference($w, self::png(8), 'Wanted');
        $w->put($w->root . '/page.zip', '<html>not a zip</html>');
        $import = new InstanceImageImport($repository, $w->root . '/cache/index.sqlite3');
        $failed = false;
        try { $import->run($w->root . '/page.zip', [ImageReference::filename($ref) => $ref]); }
        catch (RuntimeException $error) { $failed = true; assertStringContains('Invalid or corrupt image ZIP', $error->getMessage()); }
        assertTrue($failed);
        assertSame(false, is_dir((new ImageStore($repository))->root));
    }
}
