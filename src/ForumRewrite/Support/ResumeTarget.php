<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

final class ResumeTarget
{
    public static function fromRequestUri(string $requestUri): string
    {
        if ($requestUri === ''
            || trim($requestUri) !== $requestUri
            || preg_match('/[\\x00-\\x1F\\x7F\\\\]/', $requestUri) === 1
            || !str_starts_with($requestUri, '/')
            || str_starts_with($requestUri, '//')
        ) {
            return '/';
        }

        $parts = parse_url($requestUri);
        if ($parts === false
            || isset($parts['scheme'], $parts['host'])
            || !isset($parts['path'])
            || !is_string($parts['path'])
            || !str_starts_with($parts['path'], '/')
            || str_starts_with($parts['path'], '//')
        ) {
            return '/';
        }

        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $parts['path'] . $query;
    }
}
