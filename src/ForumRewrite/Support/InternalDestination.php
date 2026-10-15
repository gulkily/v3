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
            && preg_match('/[\x00-\x20\x7f\\\\]/', $value) !== 1
            && preg_match('/[\x00-\x1f\x7f\\\\]/', $decoded) !== 1
            && preg_match('//u', $decoded) === 1
            && preg_match('/%(?![a-fA-F0-9]{2})/', $value) !== 1;
    }
}
