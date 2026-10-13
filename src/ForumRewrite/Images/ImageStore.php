<?php

declare(strict_types=1);

namespace ForumRewrite\Images;

use RuntimeException;

/** Instance-private immutable files. Mutations must hold the instance write lock. */
final class ImageStore
{
    public const MAX_TOTAL_BYTES = 256 * 1024 * 1024;
    public const MAX_OBJECTS = 1024;
    public readonly string $root;

    public function __construct(string $repositoryRoot, ?string $root = null)
    {
        $configured = $root ?? (getenv('FORUM_IMAGE_ROOT') ?: dirname($repositoryRoot) . '/' . basename($repositoryRoot) . '-images');
        if (!str_starts_with($configured, '/') || str_contains($configured, '..') || str_contains($configured, "\0")) {
            throw new RuntimeException('Image root must be an absolute private directory without parent traversal.');
        }
        $this->root = rtrim($configured, '/');
        for ($path = $this->root; $path !== '/'; $path = dirname($path)) {
            if (is_link($path)) {
                throw new RuntimeException('Image root must not contain symlinks.');
            }
        }
        $public = dirname(__DIR__, 3) . '/public';
        foreach ([$repositoryRoot, $public, (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')] as $unsafe) {
            $unsafe = rtrim($unsafe, '/');
            if ($unsafe !== '' && ($this->root === $unsafe || str_starts_with($this->root, $unsafe . '/') || str_starts_with($unsafe, $this->root . '/'))) {
                throw new RuntimeException('Image root must be separate from repository and web roots.');
            }
        }
    }

    public static function inspect(string $path, string $description): array
    {
        if (is_link($path) || !is_file($path) || filesize($path) > ImageReference::MAX_BYTES) {
            throw new RuntimeException('Image must be a regular JPEG/PNG of at most 5 MiB.');
        }
        $size = @getimagesize($path);
        if ($size === false || !in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw new RuntimeException('Image is not a supported JPEG/PNG.');
        }
        return ImageReference::validate(['sha256' => hash_file('sha256', $path), 'type' => $size[2] === IMAGETYPE_PNG ? 'png' : 'jpg', 'bytes' => filesize($path), 'width' => $size[0], 'height' => $size[1], 'description' => $description]);
    }

    public function validateUpload(string $source, array $reference): void
    {
        if (self::inspect($source, $reference['description']) !== ImageReference::validate($reference)) {
            throw new RuntimeException('Image bytes do not match the signed reference.');
        }
        $inventory = $this->inventory();
        $name = ImageReference::filename($reference);
        if (!isset($inventory[$name]) && (count($inventory) >= self::MAX_OBJECTS || array_sum(array_column($inventory, 'bytes')) + $reference['bytes'] > self::MAX_TOTAL_BYTES)) {
            throw new RuntimeException('Image store capacity reached (256 MiB / 1024 images).');
        }
    }

    public function put(string $source, array $reference): void
    {
        $this->validateUpload($source, $reference);
        $this->ensureRoot();
        $target = $this->root . '/' . ImageReference::filename($reference);
        if (is_file($target)) {
            $this->resolve($reference);
            return;
        }
        $temp = tempnam($this->root, '.upload-');
        if ($temp === false) {
            throw new RuntimeException('Unable to stage image.');
        }
        try {
            if (!copy($source, $temp) || !chmod($temp, 0600) || !rename($temp, $target)) {
                throw new RuntimeException('Unable to save image; preserve the draft and retry.');
            }
        } finally {
            if (is_file($temp)) { unlink($temp); }
        }
    }

    public function resolve(array $reference): string
    {
        $path = $this->root . '/' . ImageReference::filename($reference);
        if (self::inspect($path, $reference['description']) !== ImageReference::validate($reference)) {
            throw new RuntimeException('Image unavailable or damaged; restore from a verified image backup.');
        }
        return $path;
    }

    public function inventory(): array
    {
        if (!is_dir($this->root)) { return []; }
        $result = [];
        foreach (scandir($this->root) ?: [] as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '.upload-')) { continue; }
            if (!preg_match('/^([a-f0-9]{64})\.(png|jpg)$/D', $name, $m)) {
                throw new RuntimeException('Unexpected entry in image store: ' . $name);
            }
            $r = self::inspect($this->root . '/' . $name, 'Inventory');
            if ($r['sha256'] !== $m[1] || $r['type'] !== $m[2]) {
                throw new RuntimeException('Image store checksum mismatch: ' . $name);
            }
            unset($r['description']);
            $result[$name] = $r;
            if (count($result) > self::MAX_OBJECTS || array_sum(array_column($result, 'bytes')) > self::MAX_TOTAL_BYTES) {
                throw new RuntimeException('Image store exceeds supported export capacity.');
            }
        }
        ksort($result);
        return $result;
    }

    private function ensureRoot(): void
    {
        if (!is_dir($this->root) && !mkdir($this->root, 0700, true) && !is_dir($this->root)) {
            throw new RuntimeException('Unable to create private image store.');
        }
    }
}
