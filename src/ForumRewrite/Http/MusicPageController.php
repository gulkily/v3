<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Pagination\PageNavigation;
use ForumRewrite\ReadModel\TagPage;

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

        return $this->routeServices->renderPageTemplate(
            'music.php',
            ['threads' => $selected['items'], 'pagination' => $pagination, 'paginationError' => null],
            'Music',
            'music',
        );
    }
}
