<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use Closure;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Images\{ImageBackup, ImageReference, ImageStore, ImageZipReader};
use ForumRewrite\Support\ExecutionLock;
use RuntimeException;

/**
 * Adds a source instance's images to the destination store. Only images that
 * destination posts reference are copied, so images of posts the source removed
 * never arrive. Existing images are never overwritten.
 */
final class InstanceImageImport
{
    private readonly ImageStore $store;

    public function __construct(private readonly string $repositoryRoot, private readonly string $databasePath, ?string $imageRoot = null)
    {
        $this->store = new ImageStore($repositoryRoot, $imageRoot);
    }

    /** References from destination posts, plus any extra posts a preview would add. */
    public function references(array $extra = []): array
    {
        return ImageBackup::references($this->repositoryRoot) + $extra;
    }

    /** References from source posts that a content plan would import (for previews). */
    public static function plannedReferences(string $sourceRoot, array $entries): array
    {
        $records = new CanonicalRecordRepository($sourceRoot, requireLegacyTimestampMetadata: true, signatureKeyFallbackRoot: $sourceRoot);
        $references = [];
        foreach ($entries as $path => $entry) {
            if (($entry['state'] ?? '') !== 'import' || !str_starts_with($path, 'records/posts/') || !str_ends_with($path, '.txt')) { continue; }
            try { $reference = ImageReference::fromBody($records->loadPost($path)->body); }
            catch (RuntimeException) { continue; }
            if ($reference !== null) { $references[ImageReference::filename($reference)] = $reference; }
        }
        return $references;
    }

    /**
     * Download the source's image ZIP only when some referenced image is missing,
     * then copy. A failure leaves text untouched and reports every missing image.
     *
     * @return array{status:string, added:int, present:int, missing:int, reasons:array<string,int>}
     */
    public function fromSource(string $sourceUrl, string $workspace, array $references, bool $preview, ?Closure $progress = null): array
    {
        $missing = $this->missing($references);
        if ($missing !== []) {
            $zip = $workspace . '/images.zip';
            try {
                if ($progress !== null) { $progress('Downloading image backup for ' . count($missing) . ' missing images...'); }
                (new InstanceArchiveDownloader(progress: $progress))->download($sourceUrl, $zip, InstanceArchiveDownloader::IMAGES_PATH);
                $result = $this->run($zip, $references, $preview);
            } catch (RuntimeException $error) {
                $result = ['added' => 0, 'present' => count($references) - count($missing), 'missing' => count($missing), 'reasons' => [self::failure($error) => count($missing)]];
            } finally {
                if (is_file($zip)) { unlink($zip); }
            }
        } else {
            $result = ['added' => 0, 'present' => count($references), 'missing' => 0, 'reasons' => []];
        }
        return ['status' => $result['missing'] === 0 ? 'complete' : 'partial'] + $result;
    }

    private static function failure(RuntimeException $error): string
    {
        return match ($error->getCode()) {
            403 => 'image backups are turned off on the source',
            404 => 'the source has no image download; it may run an older version',
            default => $error->getMessage(),
        };
    }

    /** @return array<string, array> references whose image the store lacks, keyed by filename */
    public function missing(array $references): array
    {
        return array_filter($references, fn (array $reference): bool => !$this->store->isPresent($reference));
    }

    /**
     * @return array{added:int, present:int, missing:int, reasons:array<string,int>}
     */
    public function run(string $zip, array $references, bool $preview = false): array
    {
        $missing = $this->missing($references);
        $result = ['added' => 0, 'present' => count($references) - count($missing), 'missing' => 0, 'reasons' => []];
        if ($missing === []) { return $result; }
        $stage = sys_get_temp_dir() . '/forum-image-import-' . bin2hex(random_bytes(10));
        if (!mkdir($stage, 0700)) { throw new RuntimeException('Unable to create private image staging directory.'); }
        try {
            ImageZipReader::unpack($zip, $stage);
            $items = [];
            foreach ($missing as $name => $reference) {
                $path = $stage . '/objects/' . $name;
                if (!is_file($path)) {
                    $this->reason($result, 'not in the source image backup');
                    continue;
                }
                $items[] = ['source' => $path, 'reference' => $reference];
            }
            if ($preview) {
                foreach ($items as $item) {
                    $ok = false;
                    try { $ok = ImageStore::inspect($item['source'], $item['reference']['description']) === ImageReference::validate($item['reference']); }
                    catch (RuntimeException) {}
                    if ($ok) { $result['added']++; } else { $this->reason($result, 'source image does not match its post'); }
                }
                return $result;
            }
            $outcome = (new ExecutionLock(dirname($this->databasePath) . '/forum-rewrite.lock'))
                ->withExclusiveLock(fn (): array => $this->store->putAll($items));
            $result['added'] = count($outcome['added']);
            $result['present'] += count($outcome['present']);
            foreach ($outcome['mismatched'] as $_) { $this->reason($result, 'source image does not match its post'); }
            foreach ($outcome['full'] as $_) { $this->reason($result, 'image store is full (256 MiB / 1024 images)'); }
            return $result;
        } finally {
            ImageBackup::removeTree($stage);
        }
    }

    private function reason(array &$result, string $reason): void
    {
        $result['missing']++;
        $result['reasons'][$reason] = ($result['reasons'][$reason] ?? 0) + 1;
    }
}
