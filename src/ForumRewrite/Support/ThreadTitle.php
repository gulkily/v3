<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

final class ThreadTitle
{
    public static function displayTitle(string $subject, string $body, string $fallbackId, int $limit = 80): string
    {
        $subject = trim($subject);
        if ($subject !== '') {
            return $subject;
        }

        $excerpt = self::bodyExcerpt($body, $limit);
        if ($excerpt !== '') {
            return $excerpt;
        }

        return trim($fallbackId) !== '' ? $fallbackId : 'Untitled thread';
    }

    private static function bodyExcerpt(string $body, int $limit): string
    {
        $body = trim(preg_replace('/\s+/u', ' ', $body) ?? $body);
        if ($body === '') {
            return '';
        }

        $limit = max(8, $limit);
        $characters = preg_split('//u', $body, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false || $characters === []) {
            return '';
        }

        if (count($characters) <= $limit) {
            return $body;
        }

        $slice = implode('', array_slice($characters, 0, $limit));
        $lastSpace = strrpos($slice, ' ');
        if ($lastSpace !== false && $lastSpace >= 24) {
            $slice = substr($slice, 0, $lastSpace);
        }

        return rtrim($slice, " \t\n\r\0\x0B.,;:!?") . '...';
    }
}
