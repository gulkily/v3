<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use ForumRewrite\Images\ImageReference;
use ForumRewrite\View\MediaEmbedDetector;

final class ThreadTitle
{
    public static function displayTitle(string $subject, string $body, string $fallbackId, int $limit = 80, bool $mediaEmbedsEnabled = false): string
    {
        $subject = trim($subject);
        if ($subject !== '') {
            return $subject;
        }

        // Untitled until the fetch lands only where that was always so; music
        // links keep showing the link itself, so a failed fetch still reads well.
        if ($mediaEmbedsEnabled && in_array(self::bareMediaEmbedMatch($subject, $body)['provider'] ?? null, ['youtube', 'instagram'], true)) {
            return 'Untitled';
        }

        try {
            $image = ImageReference::fromBody($body);
        } catch (\RuntimeException) {
            $image = null;
        }
        if ($image !== null) {
            $body = ImageReference::withoutReference($body);
        }

        $excerpt = self::bodyExcerpt($body, $limit);
        if ($excerpt !== '') {
            return $excerpt;
        }
        if ($image !== null) {
            return 'Image';
        }

        return trim($fallbackId) !== '' ? $fallbackId : 'Untitled thread';
    }

    /**
     * Returns a match only when $subject is blank and the whole (trimmed)
     * body is exactly one recognized URL whose provider supports title fetching.
     * Shared by displayTitle()'s "Untitled" fallback and the title-fetch
     * beacon, so both use the exact same bare-URL definition.
     *
     * @return ?array{provider: string, embedId: string, url: string}
     */
    /** Providers whose titles can be fetched and back-filled into an empty subject. */
    public const TITLE_PROVIDERS = ['youtube', 'instagram', 'spotify', 'soundcloud', 'tiktok', 'bandcamp'];

    /**
     * The one embed whose fetched title may fill an empty subject: the whole body
     * is that link, or, with $firstLine (FORUM_MEDIA_EMBEDS_FIRST_LINE_TITLES_ENABLED),
     * its first line is that link and the post has no other embed.
     *
     * @return ?array{provider: string, embedId: string, url: string}
     */
    public static function bareMediaEmbedMatch(string $subject, string $body, bool $firstLine = false): ?array
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
        if (!in_array($match['provider'], self::TITLE_PROVIDERS, true) || $match['offset'] !== 0) {
            return null;
        }
        $end = $match['offset'] + $match['length'];
        $wholeBody = $end === strlen($trimmedBody);
        $wholeFirstLine = $firstLine && preg_match('/\A[ \t]*\n/', substr($trimmedBody, $end)) === 1;
        if (!$wholeBody && !$wholeFirstLine) {
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
