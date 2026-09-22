<?php

declare(strict_types=1);

namespace ForumRewrite\Host;

final class HtmlResponseCache
{
    public static function etag(string $html): string
    {
        return '"' . hash('sha256', $html) . '"';
    }

    public static function requestMatches(string $etag): bool
    {
        $header = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        if ($header === '') {
            return false;
        }

        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '*' || hash_equals($etag, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
