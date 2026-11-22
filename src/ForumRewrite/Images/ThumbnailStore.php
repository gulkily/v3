<?php

declare(strict_types=1);

namespace ForumRewrite\Images;

use RuntimeException;

/**
 * Small cached copies of stored images for list rows and gallery tiles. They live beside the
 * image root (never inside it: the image store accepts only originals), are
 * named by the original's sha256 and size, and are only served while the
 * original is present, so purges and restores need no extra cleanup.
 * A derivative is disposable: deleting the directory just regenerates them.
 */
final class ThumbnailStore
{
    /**
     * Boxes in pixels by size name: the square list-row slot (4.5rem) at 1x
     * and 2x, and gallery tiles (rows are at most 12rem tall).
     */
    public const SIZES = ['sq' => [72, 72], 'sq2x' => [144, 144], 'tile' => [480, 480], 'wide' => [960, 960], 'large' => [1600, 1600]];

    /**
     * Display sizes: what a page shows in place of the stored original (the
     * home page's featured meme, an image in a thread). When WebP is available
     * they are made even from an original that already fits, because the
     * re-encoded copy is a fraction of a PNG or camera JPEG's bytes; the
     * original stays untouched and opens only when asked for.
     */
    public const DISPLAY = ['wide', 'large'];

    /**
     * WebP quality for display sizes. Thumbnails use 80; at 80 a full-size
     * painting lost its canvas grain (2.5 MB original, 23 KB copy), so the
     * sizes people actually look at are written at 90.
     */
    public const DISPLAY_QUALITY = 90;

    /**
     * Sizes cropped to fill their box from the centre, as the row slot shows
     * them with object-fit: cover; other sizes fit inside their box whole.
     */
    private const CROPPED = ['sq', 'sq2x'];

    /** Earlier list-row size names, still accepted from cached pages. */
    public const LEGACY = ['1x' => 'sq', '2x' => 'sq2x'];

    public readonly string $root;

    public function __construct(private readonly ImageStore $images)
    {
        $this->root = $images->root . '-thumbs';
    }

    public static function available(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagecreatefromjpeg') && function_exists('imagecreatefrompng');
    }

    /**
     * Derivatives are WebP when GD can write it: a resampled screenshot stored
     * as truecolor PNG runs to 200-300 KB at tile size, the same tile as WebP
     * to about 30 KB, and WebP keeps transparency. The original's type stays
     * in the name so the original can be found from the URL.
     */
    public static function format(): ?string
    {
        return function_exists('imagewebp') ? 'webp' : null;
    }

    public static function filename(array $reference, string $size): string
    {
        $format = self::format();
        return $reference['sha256'] . '-' . $size . '.' . $reference['type'] . ($format === null ? '' : '.' . $format);
    }

    /** @return array{width:int, height:int} pixel size of the derivative (never larger than the original) */
    public static function size(array $reference, string $size): array
    {
        [$width, $height] = self::box($size);
        if (in_array($size, self::CROPPED, true)) {
            $side = min($width, $reference['width'], $reference['height']);
            return ['width' => max(1, $side), 'height' => max(1, $side)];
        }
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
        $reencode = in_array($size, self::DISPLAY, true) && self::format() === 'webp';
        return !self::available() || (self::originalFits($reference, $size) && !$reencode)
            ? '/images/' . ImageReference::filename($reference)
            : '/images/thumbs/' . self::filename($reference, $size);
    }

    /**
     * What an inline image offers the browser: the tile, then each display size
     * that is wider than the one before it, with pixel widths for `srcset`. An
     * original no wider than the tile is its own only entry.
     *
     * @return list<array{url:string, width:int}>
     */
    public static function displaySources(array $reference): array
    {
        $sources = [];
        foreach (['tile', ...self::DISPLAY] as $size) {
            $width = self::size($reference, $size)['width'];
            if ($sources === [] || $width > $sources[count($sources) - 1]['width']) {
                $sources[] = ['url' => self::url($reference, $size), 'width' => $width];
            }
        }
        return $sources;
    }

    /** `srcset` for displaySources(), or null when there is only one entry. */
    public static function displaySrcset(array $reference): ?string
    {
        $sources = self::displaySources($reference);
        return count($sources) < 2 ? null : implode(', ', array_map(static fn (array $s): string => $s['url'] . ' ' . $s['width'] . 'w', $sources));
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
            $this->resize($source, $reference['type'], $temp, $pixels['width'], $pixels['height'], self::format(), in_array($size, self::CROPPED, true), in_array($size, self::DISPLAY, true) ? self::DISPLAY_QUALITY : 80);
            if (!chmod($temp, 0600) || !rename($temp, $target)) {
                throw new RuntimeException('Unable to save thumbnail.');
            }
        } finally {
            if (is_file($temp)) { unlink($temp); }
        }
        return $target;
    }

    /**
     * A page-ready stand-in for an image while it loads: a copy about 16px
     * wide as a `data:` address, a few hundred bytes, which the browser
     * stretches into a soft blur. Made once and kept beside the other copies
     * (its name is not a size, so it is never served by address). Null when
     * the host cannot write WebP or the image cannot be read.
     */
    public function inline(array $reference): ?string
    {
        if (!self::available() || self::format() !== 'webp') {
            return null;
        }
        try {
            $target = $this->root . '/' . $reference['sha256'] . '-dot.' . $reference['type'] . '.webp';
            if (!is_file($target) || is_link($target)) {
                $source = $this->images->resolve($reference);
                $width = min(16, $reference['width']);
                $height = max(1, (int) round($reference['height'] * $width / $reference['width']));
                $this->ensureRoot();
                $temp = tempnam($this->root, '.thumb-');
                if ($temp === false) {
                    return null;
                }
                try {
                    $this->resize($source, $reference['type'], $temp, $width, $height, 'webp', false, 50);
                    if (!chmod($temp, 0600) || !rename($temp, $target)) {
                        return null;
                    }
                } finally {
                    if (is_file($temp)) { unlink($temp); }
                }
            }
            $bytes = file_get_contents($target);
            return $bytes === false || strlen($bytes) > 2048 ? null : 'data:image/webp;base64,' . base64_encode($bytes);
        } catch (RuntimeException) {
            return null;
        }
    }

    private function resize(string $source, string $type, string $target, int $width, int $height, ?string $format, bool $crop, int $quality = 80): void
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
        // A cropped size takes the centred region of the original with the target's proportions.
        $sourceWidth = imagesx($original);
        $sourceHeight = imagesy($original);
        $scale = $crop ? min($sourceWidth / $width, $sourceHeight / $height) : null;
        $cropWidth = $scale === null ? $sourceWidth : (int) round($width * $scale);
        $cropHeight = $scale === null ? $sourceHeight : (int) round($height * $scale);
        $ok = imagecopyresampled($thumb, $original, 0, 0, intdiv($sourceWidth - $cropWidth, 2), intdiv($sourceHeight - $cropHeight, 2), $width, $height, $cropWidth, $cropHeight)
            && match (true) {
                $format === 'webp' => imagewebp($thumb, $target, $quality),
                // Without WebP, a 256-colour palette still cuts a PNG tile to about a third.
                $type === 'png' => imagetruecolortopalette($thumb, true, 256) && imagepng($thumb, $target, 9),
                default => imagejpeg($thumb, $target, 82),
            };
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
