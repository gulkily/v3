<?php

declare(strict_types=1);
namespace ForumRewrite\Images;

use RuntimeException;

/** Bounded reader for image backup ZIPs, shared by restore and instance import. */
final class ImageZipReader
{
    /** Validate every entry before reading by name; never extract archive paths directly. */
    public static function unpack(string $archive, string $stage): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('PHP ZIP support is unavailable. Enable the zip extension for this PHP runtime and retry.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($archive, \ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('Invalid or corrupt image ZIP archive. Download the complete backup and retry.');
        }
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > ImageStore::MAX_OBJECTS + 2) {
                throw new RuntimeException('Image ZIP has missing or excessive entries.');
            }
            $entries = []; $seen = []; $total = 0; $objects = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = $zip->getNameIndex($index, \ZipArchive::FL_ENC_RAW);
                if ($stat === false || $name === false || isset($seen[$name])
                    || !$zip->getExternalAttributesIndex($index, $system, $attributes)) {
                    throw new RuntimeException('Duplicate or unreadable image ZIP entry.');
                }
                $seen[$name] = true;
                if (($stat['encryption_method'] ?? -1) !== 0) {
                    throw new RuntimeException('Encrypted image ZIP entries are not supported.');
                }
                $type = ($attributes >> 16) & 0170000;
                $directory = $name === 'objects/';
                if ($directory && $stat['size'] === 0 && in_array($type, [0, 0040000], true)) { continue; }
                if (!in_array($type, [0, 0100000], true) || ($attributes & 0x10) !== 0
                    || ($name !== 'manifest.json' && !preg_match('#^objects/[a-f0-9]{64}\.(png|jpg)$#D', $name))) {
                    throw new RuntimeException('Unsafe image ZIP entry; links and traversal are forbidden.');
                }
                $max = $name === 'manifest.json' ? 2 * 1024 * 1024 : ImageReference::MAX_BYTES;
                $total += $stat['size'];
                if ($stat['size'] < 1 || $stat['size'] > $max || $total > ImageStore::MAX_TOTAL_BYTES + 2 * 1024 * 1024
                    || ($name !== 'manifest.json' && ++$objects > ImageStore::MAX_OBJECTS)) {
                    throw new RuntimeException('Image ZIP entry exceeds expanded capacity.');
                }
                $entries[$name] = $stat;
            }
            if (!isset($entries['manifest.json'])) { throw new RuntimeException('Image ZIP has no manifest.'); }
            $free = disk_free_space($stage);
            if ($free === false || $free < $total + 16 * 1024 * 1024) {
                throw new RuntimeException('Insufficient private staging space for expanded images. Free space and retry.');
            }
            if (!mkdir($stage . '/objects', 0700)) { throw new RuntimeException('Unable to stage image ZIP.'); }
            $manifest = null;
            foreach ($entries as $name => $stat) {
                $stream = @$zip->getStream($name);
                if ($stream === false) { throw new RuntimeException('Unable to read image ZIP entry.'); }
                try { $bytes = @stream_get_contents($stream, $stat['size'] + 1); }
                finally { fclose($stream); }
                if ($bytes === false || strlen($bytes) !== $stat['size']
                    || hash('crc32b', $bytes) !== sprintf('%08x', $stat['crc'])) {
                    throw new RuntimeException('Corrupt or truncated image ZIP entry. Download the complete backup and retry.');
                }
                if ($name === 'manifest.json') {
                    $manifest = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
                } elseif (file_put_contents($stage . '/' . $name, $bytes) !== strlen($bytes)) {
                    throw new RuntimeException('Unable to stage image ZIP entry.');
                }
            }
            if (!is_array($manifest)) { throw new RuntimeException('Image ZIP has no valid manifest.'); }
            return $manifest;
        } finally { $zip->close(); }
    }
}
