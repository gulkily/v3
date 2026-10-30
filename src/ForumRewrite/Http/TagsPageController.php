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

    public function tag(string $tag, mixed $position = null): ?string
    {
        $tag = strtolower($tag);
        $error = null;
        try {
            if ($position !== null && !is_string($position)) throw new \InvalidArgumentException('This page position is invalid.');
            $cursor = $position === null ? null : PageNavigation::decode($position, TagPage::context($tag));
            $page = TagPage::page($this->routeServices->pdo(), $tag, $cursor['boundary'] ?? null, $cursor['direction'] ?? 'next');
        } catch (\InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            $page = ['items' => [], 'previous' => null, 'next' => null, 'exists' => true];
        }
        if (!$page['exists'] && $position === null) return null;
        $group = ['tag' => $tag, 'threads' => $page['items']];
        $pagination = PageNavigation::links('/tags/' . $tag, [], TagPage::context($tag), $page['previous'], $page['next'],
            $error !== null || ($position !== null && $page['items'] === []));

        return $this->routeServices->renderPageTemplate(
            'tag.php',
            [
                'group' => $group,
                'pagination' => $pagination,
                'paginationError' => $error,
            ],
            '#' . $group['tag'] . ' - Tag',
            'board',
        );
    }
}
