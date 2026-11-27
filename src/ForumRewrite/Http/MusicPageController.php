<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Pagination\PageNavigation;
use ForumRewrite\ReadModel\TagPage;
use ForumRewrite\View\MediaEmbedDetector;

/**
 * The Music page (/music/) on sites whose profile sets `musicSection`: the
 * threads tagged #music, newest activity first, exactly as /tags/music lists
 * them. Rendered live like /gallery/.
 */
final class MusicPageController
{
    public const TAG = 'music';

    public function __construct(private readonly RouteServices $routeServices) {}

    public function page(mixed $page = null): string
    {
        $number = PageNavigation::pageNumber($page);
        $selected = TagPage::page($this->routeServices->pdo(), self::TAG, $number);
        $pagination = PageNavigation::numbered('/music/', [], $number, $selected['has_next'], $selected['items'] === []);
        // Each row plays the opening post's first recognized link; a row without one shows no player.
        $detector = new MediaEmbedDetector();
        $threads = array_map(static function (array $thread) use ($detector): array {
            $first = $detector->detect((string) ($thread['body_preview'] ?? ''))[0] ?? null;
            return $thread + ['music_link' => $first === null ? null : $first['url']];
        }, $selected['items']);

        return $this->routeServices->renderPageTemplate(
            'music.php',
            [
                'threads' => $threads,
                'pagination' => $pagination,
                'paginationError' => null,
                // The share box: the Board's compact composer with the tag preset; Post returns here.
                'boardTags' => self::TAG,
                'subject' => '',
                'body' => '',
                'notice' => null,
                'error' => null,
            ],
            'Music',
            'music',
            // The Board's composer and its scripts: one line that grows on focus.
            ['/assets/inline_reply_form.js', '/assets/lazy_compose_signing.js', '/assets/outbox_store.js', '/assets/outbox_storage.js', '/assets/outbox_compose.js', '/assets/outbox_sender.js'],
        );
    }
}
