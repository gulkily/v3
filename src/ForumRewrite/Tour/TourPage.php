<?php

declare(strict_types=1);

namespace ForumRewrite\Tour;

/** Renders the tour page and prepares snapshots for it. */
final class TourPage
{
    /** @param list<array{name: string, route: string, title: string, paragraphs: list<string>}> $sections */
    public static function render(string $projectRoot, array $sections, string $siteName): string
    {
        ob_start();
        try {
            (static function (string $__template, array $sections, string $siteName): void {
                require $__template;
            })($projectRoot . '/templates/tour/page.php', $sections, $siteName);
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * A snapshot opens links in the page around it (same tab, never a new
     * one) and stays out of search results.
     */
    public static function prepareSnapshot(string $html): string
    {
        $head = '<meta name="robots" content="noindex"><base target="_top">';
        $html = preg_replace('/<meta\s+name="robots"[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<base\s[^>]*>/i', '', $html) ?? $html;
        if (preg_match('/<head(\s[^>]*)?>/i', $html, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return str_ireplace('</head>', $head . '</head>', $html);
        }
        $end = $match[0][1] + strlen($match[0][0]);
        return substr($html, 0, $end) . $head . substr($html, $end);
    }
}
