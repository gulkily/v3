<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use ForumRewrite\View\MediaEmbedDetector;

final class ThreadTitle
{
    public static function displayTitle(string $subject, string $body, string $fallbackId, int $limit = 80, bool $mediaEmbedsEnabled = false): string
    {
        $subject = trim($subject);
        if ($subject !== '') {
            return $subject;
        }

        if ($mediaEmbedsEnabled && self::bareMediaEmbedMatch($subject, $body) !== null) {
            return 'Untitled';
        }

        $excerpt = self::bodyExcerpt($body, $limit);
        if ($excerpt !== '') {
            return $excerpt;
        }

        return trim($fallbackId) !== '' ? $fallbackId : 'Untitled thread';
    }

    /**
     * Returns a match only when $subject is blank and the whole (trimmed)
     * body is exactly one recognized media-embed URL - nothing else.
     * Shared by displayTitle()'s "Untitled" fallback and the title-fetch
     * beacon, so both use the exact same bare-URL definition.
     *
     * @return ?array{provider: string, embedId: string, url: string}
     */
    public static function bareMediaEmbedMatch(string $subject, string $body): ?array
    {
        if (trim($subject) !== '') {
            return null;
        }

        $trimmedBody = trim($body);
        if ($trimmedBody === '') {
            return null;
        }

        $matches = (new MediaEmbedDetector())->detect($trimmedBody);
        if (count($matches) !== 1) {
            return null;
        }

        $match = $matches[0];
        if ($match['offset'] !== 0 || $match['offset'] + $match['length'] !== strlen($trimmedBody)) {
            return null;
        }

        return [
            'provider' => $match['provider'],
            'embedId' => $match['embedId'],
            'url' => $match['url'],
        ];
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
