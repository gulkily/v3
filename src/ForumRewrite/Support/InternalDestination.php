<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

final class InternalDestination
{
    public static function isValid(string $value): bool
    {
        $decoded = rawurldecode($value);
        return $value !== '' && strlen($value) <= 500
            && str_starts_with($decoded, '/') && !str_starts_with($decoded, '//')
            && preg_match('/[\x00-\x20\x7f\\\\]/', $decoded) !== 1;
    }
}
