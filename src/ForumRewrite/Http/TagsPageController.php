<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\TagGrouping;
use ForumRewrite\ReadModel\TagPage;
use ForumRewrite\Pagination\PageNavigation;
use ForumRewrite\ReadModel\ThreadRepository;

/**
 * Fourth Phase 2 slice of the Application.php decomposition (see
 * docs/plans/codebase_cleanup_audit_plan_v1.md and
 * docs/plans/codebase_cleanup_audit_findings_v1.md): /tags/ and
 * /tags/{tag} (or several tags joined by `+`). The original slice 1 candidate, deferred until
 * ThreadRepository/TagGrouping/BoardViewOptions existed - needs no bound
 * closures at all, unlike every other slice so far.
 */
final class TagsPageController
{
    private const TAG_GROUP_PREVIEW_LIMIT = 5;
    private const MAX_TAGS = 4;

    public function __construct(
        private readonly RouteServices $routeServices,
    ) {
    }

    public function index(): string
    {
        $view = BoardViewOptions::normalizeView('all');
        $sort = BoardViewOptions::normalizeSort('newest');
        $threads = ThreadRepository::iterateThreads($this->routeServices->pdo());
        $sortOptions = BoardViewOptions::sortOptions($view, $sort);

        return $this->routeServices->renderPageTemplate(
            'tags.php',
            [
                'tagGroups' => TagGrouping::previewsFromUniqueThreads($threads, self::TAG_GROUP_PREVIEW_LIMIT),
                'viewOptions' => BoardViewOptions::viewOptions($view, $sort),
                'sortOptions' => $sortOptions,
                'sortLabel' => BoardViewOptions::activeLabel($sortOptions, $sort),
            ],
            'Tags',
            'board',
        );
    }

    /** Where a tag `with` link lands, or null to show the tag normally. */
    public function tagWithLocation(string $tag, mixed $with): ?string
    {
        $id = PageNavigation::withId($with);
        $tags = self::tags($tag);
        if ($id === null || $tags === null) return null;
        $page = TagPage::pageOf($this->routeServices->pdo(), $tags, $id);
        return $page === null ? null : PageNavigation::withLocation('/tags/' . implode('+', $tags), [], $page, $id);
    }

    public function tag(string $tag, mixed $page = null): ?string
    {
        $tags = self::tags($tag);
        if ($tags === null) return null;
        $number = PageNavigation::pageNumber($page);
        $page = TagPage::page($this->routeServices->pdo(), $tags, $number);
        if (!$page['exists'] && $number === 1) return null;
        $group = ['tag' => implode(' + #', $tags), 'threads' => $page['items']];
        $pagination = PageNavigation::numbered('/tags/' . implode('+', $tags), [], $number, $page['has_next'], $page['items'] === []);
        return $this->routeServices->renderPageTemplate(
            'tag.php',
            [
                'group' => $group,
                'pagination' => $pagination,
                'paginationError' => null,
            ],
            '#' . $group['tag'] . ' - Tag',
            'board',
        );
    }

    /**
     * The tags a /tags/ path names: one tag, or up to four joined by `+`, all
     * required. Null when a tag repeats or there are too many.
     *
     * @return list<string>|null
     */
    private static function tags(string $path): ?array
    {
        $tags = explode('+', strtolower($path));
        if (count($tags) > self::MAX_TAGS || count(array_unique($tags)) !== count($tags)) return null;
        return $tags;
    }
}
