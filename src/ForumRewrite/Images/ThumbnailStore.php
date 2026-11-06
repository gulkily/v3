<?php

declare(strict_types=1);

namespace ForumRewrite\Images;

use RuntimeException;

/**
 * Small cached copies of stored images for list rows. They live beside the
 * image root (never inside it: the image store accepts only originals), are
 * named by the original's sha256 and scale, and are only served while the
 * original is present, so purges and restores need no extra cleanup.
 * A derivative is disposable: deleting the directory just regenerates them.
 */
final class ThumbnailStore
{
    /** Bounding box in pixels at 1x; the list slot is 3.5rem wide. */
    public const BOX_WIDTH = 64;
    public const BOX_HEIGHT = 128;
    public const SCALES = [1, 2];

    public readonly string $root;

    public function __construct(private readonly ImageStore $images)
    {
        $this->root = $images->root . '-thumbs';
    }

    public static function available(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagecreatefromjpeg') && function_exists('imagecreatefrompng');
    }

    public static function filename(array $reference, int $scale): string
    {
        return $reference['sha256'] . '-' . $scale . 'x.' . $reference['type'];
    }

    /** @return array{width:int, height:int} size of the derivative at this scale (never larger than the original) */
    public static function size(array $reference, int $scale): array
    {
        $ratio = min(1.0, (self::BOX_WIDTH * $scale) / $reference['width'], (self::BOX_HEIGHT * $scale) / $reference['height']);
        return [
            'width' => max(1, (int) round($reference['width'] * $ratio)),
            'height' => max(1, (int) round($reference['height'] * $ratio)),
        ];
    }

    /** True when the original is already at most this size, so the original itself is the thumbnail. */
    public static function originalFits(array $reference, int $scale): bool
    {
        return $reference['width'] <= self::BOX_WIDTH * $scale && $reference['height'] <= self::BOX_HEIGHT * $scale;
    }

    /**
     * Path to the cached derivative, generating it from the verified original
     * on first use. Throws when the original is unavailable or GD fails; the
     * caller then falls back to the original.
     */
    public function path(array $reference, int $scale): string
    {
        if (!in_array($scale, self::SCALES, true)) {
            throw new RuntimeException('Unsupported thumbnail scale.');
        }
        $target = $this->root . '/' . self::filename($reference, $scale);
        if (is_file($target) && !is_link($target)) {
            return $target;
        }
        if (!self::available()) {
            throw new RuntimeException('Image resizing is unavailable.');
        }
        $source = $this->images->resolve($reference);
        $size = self::size($reference, $scale);
        $this->ensureRoot();
        $temp = tempnam($this->root, '.thumb-');
        if ($temp === false) {
            throw new RuntimeException('Unable to stage thumbnail.');
        }
        try {
            $this->resize($source, $reference['type'], $temp, $size['width'], $size['height']);
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
