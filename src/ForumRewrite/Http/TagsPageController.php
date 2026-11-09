<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ProfilePresentationContent;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\ReadModel\TagGrouping;
use ForumRewrite\ReadModel\TagPage;
use ForumRewrite\Pagination\PageNavigation;
use ForumRewrite\ReadModel\ThreadRepository;

/**
 * Fourth Phase 2 slice of the Application.php decomposition (see
 * docs/plans/codebase_cleanup_audit_plan_v1.md and
 * docs/plans/codebase_cleanup_audit_findings_v1.md): /tags/ and
 * /tags/{tag}. The original slice 1 candidate, deferred until
 * ThreadRepository/TagGrouping/BoardViewOptions existed - needs no bound
 * closures at all, unlike every other slice so far.
 */
final class TagsPageController
{
    private const TAG_GROUP_PREVIEW_LIMIT = 5;

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
        if ($id === null) return null;
        $tag = strtolower($tag);
        $page = TagPage::pageOf($this->routeServices->pdo(), $tag, $id);
        return $page === null ? null : PageNavigation::withLocation('/tags/' . $tag, [], $page, $id);
    }

    public function tag(string $tag, mixed $page = null): ?string
    {
        $tag = strtolower($tag);
        $number = PageNavigation::pageNumber($page);
        $page = TagPage::page($this->routeServices->pdo(), $tag, $number);
        $jobCategory = ProfilePresentationContent::jobCategories(SiteProfileRegistry::active())[$tag] ?? null;
        if (!$page['exists'] && $number === 1 && $jobCategory === null) return null;
        $group = ['tag' => $tag, 'threads' => $page['items']];
        $pagination = PageNavigation::numbered('/tags/' . $tag, [], $number, $page['has_next'], $page['items'] === []);
        return $this->routeServices->renderPageTemplate(
            'tag.php',
            [
                'group' => $group,
                'jobCategory' => $jobCategory,
                'pagination' => $pagination,
                'paginationError' => null,
            ],
            $jobCategory['title'] ?? ('#' . $group['tag'] . ' - Tag'),
            'board',
        );
    }
}
