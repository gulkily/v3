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
        return self::TEXT;
    }
}
