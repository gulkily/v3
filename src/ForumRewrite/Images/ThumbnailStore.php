<?php

declare(strict_types=1);

namespace ForumRewrite\Images;

use RuntimeException;

/**
 * Small cached copies of stored images for list rows and gallery tiles. They live beside the
 * image root (never inside it: the image store accepts only originals), are
 * named by the original's sha256 and scale, and are only served while the
 * original is present, so purges and restores need no extra cleanup.
 * A derivative is disposable: deleting the directory just regenerates them.
 */
final class ThumbnailStore
{
    /**
     * Bounding boxes in pixels by size name: list rows (the slot is 3.5rem
     * wide) at 1x and 2x, and gallery tiles (rows are at most 12rem tall).
     */
    public const SIZES = ['1x' => [64, 128], '2x' => [128, 256], 'tile' => [480, 480]];

    public readonly string $root;

    public function __construct(private readonly ImageStore $images)
    {
        $this->root = $images->root . '-thumbs';
    }

    public static function available(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagecreatefromjpeg') && function_exists('imagecreatefrompng');
    }

    public static function filename(array $reference, string $size): string
    {
        return $reference['sha256'] . '-' . $size . '.' . $reference['type'];
    }

    /** @return array{width:int, height:int} pixel size of the derivative (never larger than the original) */
    public static function size(array $reference, string $size): array
    {
        [$width, $height] = self::box($size);
        $ratio = min(1.0, $width / $reference['width'], $height / $reference['height']);
        return [
            'width' => max(1, (int) round($reference['width'] * $ratio)),
            'height' => max(1, (int) round($reference['height'] * $ratio)),
        ];
    }

    /** True when the original is already at most this size, so the original itself is the thumbnail. */
    public static function originalFits(array $reference, string $size): bool
    {
        [$width, $height] = self::box($size);
        return $reference['width'] <= $width && $reference['height'] <= $height;
    }

    /** URL of the derivative, or of the original when resizing is unavailable or the original already fits. */
    public static function url(array $reference, string $size): string
    {
        return !self::available() || self::originalFits($reference, $size)
            ? '/images/' . ImageReference::filename($reference)
            : '/images/thumbs/' . self::filename($reference, $size);
    }

    /**
     * Path to the cached derivative, generating it from the verified original
     * on first use. Throws when the original is unavailable or GD fails; the
     * caller then falls back to the original.
     */
    public function path(array $reference, string $size): string
    {
        self::box($size);
        $target = $this->root . '/' . self::filename($reference, $size);
        if (is_file($target) && !is_link($target)) {
            return $target;
        }
        if (!self::available()) {
            throw new RuntimeException('Image resizing is unavailable.');
        }
        $source = $this->images->resolve($reference);
        $pixels = self::size($reference, $size);
        $this->ensureRoot();
        $temp = tempnam($this->root, '.thumb-');
        if ($temp === false) {
            throw new RuntimeException('Unable to stage thumbnail.');
        }
        try {
            $this->resize($source, $reference['type'], $temp, $pixels['width'], $pixels['height']);
            if (!chmod($temp, 0600) || !rename($temp, $target)) {
                throw new RuntimeException('Unable to save thumbnail.');
            }
        } finally {
            if (is_file($temp)) { unlink($temp); }
        }
        return $target;
    }

    private function resize(string $source, string $type, string $target, int $width, int $height): void
    {
        $original = $type === 'png' ? @imagecreatefrompng($source) : @imagecreatefromjpeg($source);
        if ($original === false) {
            throw new RuntimeException('Unable to decode image.');
        }
        $thumb = imagecreatetruecolor($width, $height);
        if ($type === 'png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
        }
        $ok = imagecopyresampled($thumb, $original, 0, 0, 0, 0, $width, $height, imagesx($original), imagesy($original))
            && ($type === 'png' ? imagepng($thumb, $target, 9) : imagejpeg($thumb, $target, 82));
        unset($original, $thumb);
        if (!$ok) {
            throw new RuntimeException('Unable to resize image.');
        }
    }

    /** @return array{0:int, 1:int} */
    private static function box(string $size): array
    {
        return self::SIZES[$size] ?? throw new RuntimeException('Unsupported thumbnail size.');
    }

    private function ensureRoot(): void
    {
        if (is_link($this->root)) {
            throw new RuntimeException('Thumbnail root must not be a symlink.');
        }
        if (!is_dir($this->root) && !@mkdir($this->root, 0700, true) && !is_dir($this->root)) {
            throw new RuntimeException('Unable to create thumbnail directory.');
        }
    }
}
