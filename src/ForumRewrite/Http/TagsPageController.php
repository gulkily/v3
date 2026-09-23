<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\TagGrouping;
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
        $threads = ThreadRepository::fetchThreads($this->routeServices->pdo());

        return $this->routeServices->renderPageTemplate(
            'tags.php',
            [
                'tagGroups' => TagGrouping::limitPreview(TagGrouping::byTag($threads), self::TAG_GROUP_PREVIEW_LIMIT),
                'viewOptions' => BoardViewOptions::viewOptions($view, $sort),
                'sortOptions' => BoardViewOptions::sortOptions($view, $sort),
            ],
            'Tags',
            'board',
        );
    }

    public function tag(string $tag): ?string
    {
        $threads = ThreadRepository::fetchThreads($this->routeServices->pdo());
        $group = TagGrouping::find(TagGrouping::byTag($threads), $tag);
        if ($group === null) {
            return null;
        }

        return $this->routeServices->renderPageTemplate(
            'tag.php',
            [
                'group' => $group,
            ],
            '#' . $tag . ' - Tag',
            'board',
        );
    }
}
