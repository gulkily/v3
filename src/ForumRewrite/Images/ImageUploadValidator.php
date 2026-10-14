<?php
declare(strict_types=1);

namespace ForumRewrite\Images;

use RuntimeException;

/** New uploads only: never apply this policy to historical reads or restores. */
final class ImageUploadValidator
{
    public const MAX_SIDE = 2048;

    public static function available(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagetypes')
            && (imagetypes() & IMG_JPG) !== 0 && (imagetypes() & IMG_PNG) !== 0;
    }

    public static function validate(string $path): void
    {
        if (!self::available()) {
            throw new RuntimeException('Image uploads require PHP GD with JPEG/PNG support. Ask the operator to enable it; your text draft is safe.');
        }
        $ref = ImageStore::inspect($path, '');
        if (max($ref['width'], $ref['height']) > self::MAX_SIDE) {
            throw new RuntimeException('Prepare the image at no more than 2048 pixels per side before uploading.');
        }
        $bytes = file_get_contents($path);
        if ($bytes === false) { throw new RuntimeException('Unable to read image upload.'); }
        if ($ref['type'] === 'png') {
            self::validatePng($bytes);
        } elseif (!str_ends_with($bytes, "\xff\xd9")) {
            throw new RuntimeException('JPEG is truncated or contains trailing data.');
        }
        // Dimensions and compressed size are bounded before allocating decoded pixels.
        set_error_handler(static function (): never { throw new RuntimeException('Image is damaged or cannot be decoded.'); });
        try {
            $decoded = imagecreatefromstring($bytes);
            if ($decoded === false) { throw new RuntimeException('Image cannot be decoded.'); }
            imagedestroy($decoded);
        } finally {
            restore_error_handler();
        }
    }

    private static function validatePng(string $bytes): void
    {
        $offset = 8;
        $length = strlen($bytes);
        while ($offset + 12 <= $length) {
            $size = unpack('N', substr($bytes, $offset, 4))[1];
            if ($size > $length - $offset - 12) { break; }
            $type = substr($bytes, $offset + 4, 4);
            if (in_array($type, ['acTL', 'fcTL', 'fdAT'], true)) {
                throw new RuntimeException('Upload a still preview, not an animated PNG.');
            }
            if (hash('crc32b', substr($bytes, $offset + 4, $size + 4), true) !== substr($bytes, $offset + 8 + $size, 4)) {
                throw new RuntimeException('PNG checksum is invalid.');
            }
            $offset += $size + 12;
            if ($type === 'IEND') {
                if ($size === 0 && $offset === $length) { return; }
                break;
            }
        }
        throw new RuntimeException('PNG is truncated or contains trailing data.');
    }
}
