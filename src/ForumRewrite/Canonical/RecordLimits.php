<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class RecordLimits
{
    public const TEXT = 16 * 1024 * 1024;
    public const ARMOR = 1024 * 1024;
    public const TIMESTAMP = 4096;

    public static function bytes(string $path): int
    {
        if (str_ends_with($path, '.asc') || str_ends_with($path, '.sig')) return self::ARMOR;
        if (str_contains($path, '/post-timestamps/')) return self::TIMESTAMP;
        if (str_contains($path, '/post-removals/') || str_contains($path, '/post-retractions/') || str_contains($path, '/post-retraction-acceptances/')) return self::ARMOR;
        return self::TEXT;
    }
    public static function assertFile(string $path, ?int $limit = null): void
    {
        $limit ??= self::bytes($path);
        clearstatcache(true, $path);
        $size = @filesize($path);
        if ($size === false || is_link($path) || !is_file($path)) throw new RecordReadException('Unable to read regular canonical file: ' . $path);
        if ($size > $limit) throw new RecordSizeException('Record exceeds ' . ($limit === self::TEXT ? '16 MiB parser limit' : $limit . ' byte limit') . ': ' . $path);
    }

    public static function read(string $path, ?int $limit = null): string
    {
        $limit ??= self::bytes($path);
        self::assertFile($path, $limit);
        $handle = @fopen($path, 'rb');
        if ($handle === false) throw new RecordReadException('Unable to open canonical file: ' . $path);
        try {
            // Also bound the actual read, including a file that grows after stat.
            $bytes = stream_get_contents($handle, $limit + 1);
            if ($bytes === false) throw new RecordReadException('Unable to read canonical file: ' . $path);
            if (strlen($bytes) > $limit) throw new RecordSizeException('Record exceeds ' . $limit . ' byte limit: ' . $path);
            return $bytes;
        } finally { fclose($handle); }
    }
}
