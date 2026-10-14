<?php

declare(strict_types=1);
namespace ForumRewrite\Images;

use ForumRewrite\Support\ExecutionLock;
use RuntimeException;

final class ImageRestore
{
    public function run(string $archive, string $repository, string $database, ?string $imageRoot = null, bool $verifyOnly = false): array
    {
        $store = new ImageStore($repository, $imageRoot);
        if (!is_file($archive) || is_link($archive) || filesize($archive) > ImageStore::MAX_TOTAL_BYTES + 4 * 1024 * 1024) {
            throw new RuntimeException('Image archive is missing or exceeds supported capacity.');
        }
        $parent = dirname($store->root);
        if (!is_dir($parent)) { throw new RuntimeException('Create the private image parent directory first.'); }
        return (new ExecutionLock(dirname($database) . '/forum-rewrite.lock'))->withExclusiveLock(function () use ($archive, $repository, $database, $store, $verifyOnly, $parent): array {
            $stage = $parent . '/.image-restore-' . bin2hex(random_bytes(10));
            if (disk_free_space($parent) < filesize($archive) + 16 * 1024 * 1024 || !mkdir($stage, 0700)) {
                throw new RuntimeException('Insufficient private staging space for image verification.');
            }
            try {
                $manifest = $this->unpack($archive, $stage);
                $head = (new ImageBackup($repository, $database))->revision();
                if (($manifest['schema'] ?? '') !== 'forum-images-v1' || ($manifest['repository_head'] ?? '') !== $head || !is_array($manifest['objects'] ?? null)) {
                    throw new RuntimeException('Image backup and repository revision do not match. Restore the matching repository snapshot first.');
                }
                exec('git -C ' . escapeshellarg($repository) . ' status --porcelain -- records 2>&1', $statusOutput, $exit);
                if ($exit !== 0 || implode('', $statusOutput) !== '') { throw new RuntimeException('Restore requires clean canonical records.'); }
                $stagedStore = new ImageStore($repository, $stage . '/objects');
                $actual = $stagedStore->inventory();
                $expected = $manifest['objects'];
                ksort($expected);
                if ($actual !== $expected) { throw new RuntimeException('Image inventory or checksums do not match archive contents.'); }
                foreach (ImageBackup::references($repository) as $ref) { $stagedStore->resolve($ref); }
                if (!$verifyOnly) {
                    if (file_exists($store->root)) {
                        if ($store->inventory() !== $actual) { throw new RuntimeException('Existing image store differs; restore to a fresh directory. Nothing was overwritten.'); }
                    } elseif (!rename($stage . '/objects', $store->root)) {
                        throw new RuntimeException('Unable to activate verified image store. Retry with a fresh private directory.');
                    }
                }
                return ['status' => $verifyOnly ? 'verified' : 'restored', 'repository_head' => $head, 'objects' => count($actual), 'image_root' => $store->root];
            } finally {
                ImageBackup::removeTree($stage);
            }
        });
    }

    /** Strict bounded tar reader: never asks an extractor to interpret untrusted paths. */
    private function unpack(string $archive, string $stage): array
    {
        $stream = fopen($archive, 'rb');
        if ($stream === false) { throw new RuntimeException('Unable to read image archive.'); }
        mkdir($stage . '/objects', 0700);
        $seen = []; $total = 0; $manifest = null;
        try {
            while (true) {
                $header = $this->read($stream, 512);
                if ($header === str_repeat("\0", 512)) {
                    if ($this->read($stream, 512) !== str_repeat("\0", 512)) { throw new RuntimeException('Invalid archive terminator.'); }
                    while (!feof($stream)) {
                        $tail = fread($stream, 8192);
                        if ($tail === false || trim($tail, "\0") !== '') { throw new RuntimeException('Unexpected data after image archive.'); }
                    }
                    break;
                }
                $checksum = $this->octal(substr($header, 148, 8));
                $sum = array_sum(unpack('C*', substr_replace($header, str_repeat(' ', 8), 148, 8)));
                if ($sum !== $checksum) { throw new RuntimeException('Invalid tar header checksum.'); }
                $name = rtrim(substr($header, 0, 100), "\0");
                $prefix = rtrim(substr($header, 345, 155), "\0");
                $type = $header[156];
                $size = $this->octal(substr($header, 124, 12));
                if ($prefix !== '' || isset($seen[$name]) || count($seen) > ImageStore::MAX_OBJECTS + 1) { throw new RuntimeException('Duplicate, prefixed or excessive archive entries.'); }
                $seen[$name] = true;
                if ($type === '5' && in_array($name, ['objects', 'objects/'], true) && $size === 0) { continue; }
                if (!in_array($type, ["\0", '0'], true) || ($name !== 'manifest.json' && !preg_match('#^objects/[a-f0-9]{64}\.(png|jpg)$#D', $name))) {
                    throw new RuntimeException('Unsafe image archive entry; links and traversal are forbidden.');
                }
                $max = $name === 'manifest.json' ? 2 * 1024 * 1024 : ImageReference::MAX_BYTES;
                $total += $size;
                if ($size < 1 || $size > $max || $total > ImageStore::MAX_TOTAL_BYTES + 2 * 1024 * 1024) { throw new RuntimeException('Image archive entry exceeds capacity.'); }
                $bytes = $this->read($stream, $size);
                $this->read($stream, (512 - $size % 512) % 512);
                if ($name === 'manifest.json') {
                    $manifest = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
                } elseif (file_put_contents($stage . '/' . $name, $bytes) !== $size) {
                    throw new RuntimeException('Unable to stage image archive.');
                }
            }
        } finally { fclose($stream); }
        if (!is_array($manifest)) { throw new RuntimeException('Image archive has no valid manifest.'); }
        return $manifest;
    }

    private function octal(string $field): int
    {
        $value = trim($field, " \0");
        if ($value === '' || !preg_match('/^[0-7]+$/D', $value)) { throw new RuntimeException('Unsupported tar number.'); }
        return intval($value, 8);
    }

    private function read($stream, int $size): string
    {
        $data = '';
        while (strlen($data) < $size) {
            $chunk = fread($stream, $size - strlen($data));
            if ($chunk === false || $chunk === '') { throw new RuntimeException('Truncated image archive.'); }
            $data .= $chunk;
        }
        return $data;
    }
}
