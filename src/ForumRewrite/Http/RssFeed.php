<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

/**
 * Minimal RSS 2.0 XML building - pure string formatting, no Application
 * state. Shared by the board, thread, activity, and tag RSS feeds. See
 * docs/plans/codebase_cleanup_audit_findings_v1.md, Phase 2.
 */
final class RssFeed
{
    /**
     * @param string[] $items
     */
    public static function feed(string $title, string $link, array $items): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rss version="2.0"><channel><title>' . self::escape($title) . '</title>'
            . '<link>' . self::escape('http://localhost' . $link) . '</link>'
            . '<description>' . self::escape($title . ' feed') . '</description>'
            . implode('', $items)
            . '</channel></rss>';
    }

    public static function item(string $title, string $link, string $description, ?string $publishedAt = null): string
    {
        $item = '<item><title>' . self::escape($title) . '</title>'
            . '<link>' . self::escape('http://localhost' . $link) . '</link>'
            . '<description>' . self::escape($description) . '</description>';

        if ($publishedAt !== null && $publishedAt !== '') {
            $timestamp = strtotime($publishedAt);
            if ($timestamp !== false) {
                $item .= '<pubDate>' . self::escape(gmdate(DATE_RSS, $timestamp)) . '</pubDate>';
            }
        }

        return $item . '</item>';
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
