<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\Support\ThreadTitle;

/**
 * Fifth Phase 2 slice of the Application.php decomposition (see
 * docs/plans/codebase_cleanup_audit_plan_v1.md and
 * docs/plans/codebase_cleanup_audit_findings_v1.md): the board itself
 * (/ and /threads/, both the HTML and RSS forms). Built on
 * ThreadRepository and BoardViewOptions, extracted earlier in this slice.
 *
 * The view-matching and sort-comparator methods (matchesView, compare*)
 * were confirmed single-caller (only reachable through this controller's
 * own board() method) before moving here wholesale, unlike the query-layer
 * pieces extracted earlier which had multiple independent callers.
 */
final class BoardPageController
{
    public function __construct(
        private readonly RouteServices $routeServices,
    ) {
    }

    public function board(string $view, string $sort): string
    {
        $view = BoardViewOptions::normalizeView($view);
        $sort = BoardViewOptions::normalizeSort($sort);
        $viewOptions = BoardViewOptions::viewOptions($view, $sort);
        $sortOptions = BoardViewOptions::sortOptions($view, $sort);

        return $this->routeServices->renderPageTemplate(
            'board.php',
            [
                'threads' => $this->fetchBoardThreads($view, $sort),
                'view' => $view,
                'sort' => $sort,
                'viewOptions' => $viewOptions,
                'sortOptions' => $sortOptions,
                'viewLabel' => BoardViewOptions::activeLabel($viewOptions, $view),
                'sortLabel' => BoardViewOptions::activeLabel($sortOptions, $sort),
            ],
            'Board',
            'board',
            [
                '/assets/inline_reply_form.js',
                '/assets/lazy_compose_signing.js',
            ],
        );
    }

    public function rss(): string
    {
        $items = [];
        foreach (ThreadRepository::fetchThreads($this->routeServices->pdo()) as $thread) {
            $title = $this->displayThreadTitle($thread);
            $items[] = RssFeed::item($title, '/threads/' . $thread['root_post_id'], $thread['body_preview'], (string) $thread['last_activity_at']);
        }

        return RssFeed::feed('Board', '/?format=rss', $items);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchBoardThreads(string $view, string $sort): array
    {
        $threads = array_values(array_filter(
            ThreadRepository::fetchThreads($this->routeServices->pdo()),
            fn (array $thread): bool => $this->matchesView($thread, $view)
        ));

        usort($threads, fn (array $left, array $right): int => $this->compareThreads($left, $right, $sort));

        return $threads;
    }

    /**
     * @param array<string, mixed> $thread
     */
    private function matchesView(array $thread, string $view): bool
    {
        return match ($view) {
            'all' => true,
            'liked' => in_array('like', $thread['thread_labels'] ?? [], true)
                && ((int) ($thread['root_post_score_total'] ?? 0)) >= 0,
            default => true,
        };
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareThreads(array $left, array $right, string $sort): int
    {
        $pinnedCompare = $this->comparePinnedStatus($left, $right);
        if ($pinnedCompare !== 0) {
            return $pinnedCompare;
        }

        return match ($sort) {
            'oldest' => $this->compareOldest($left, $right),
            'top' => $this->compareTop($left, $right),
            default => $this->compareNewest($left, $right),
        };
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function comparePinnedStatus(array $left, array $right): int
    {
        return ((int) $this->isPinned($right)) <=> ((int) $this->isPinned($left));
    }

    /**
     * @param array<string, mixed> $thread
     */
    private function isPinned(array $thread): bool
    {
        return in_array('pinned', $thread['thread_labels'] ?? [], true);
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareNewest(array $left, array $right): int
    {
        $createdCompare = strcmp((string) $right['root_post_created_at'], (string) $left['root_post_created_at']);
        if ($createdCompare !== 0) {
            return $createdCompare;
        }

        return strcmp((string) $right['root_post_id'], (string) $left['root_post_id']);
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareOldest(array $left, array $right): int
    {
        $createdCompare = strcmp((string) $left['root_post_created_at'], (string) $right['root_post_created_at']);
        if ($createdCompare !== 0) {
            return $createdCompare;
        }

        return strcmp((string) $left['root_post_id'], (string) $right['root_post_id']);
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareTop(array $left, array $right): int
    {
        $scoreCompare = ((int) $right['score_total']) <=> ((int) $left['score_total']);
        if ($scoreCompare !== 0) {
            return $scoreCompare;
        }

        return $this->compareNewest($left, $right);
    }

    /**
     * @param array<string, mixed> $thread
     */
    private function displayThreadTitle(array $thread): string
    {
        return ThreadTitle::displayTitle(
            (string) ($thread['subject'] ?? ''),
            (string) ($thread['body_preview'] ?? $thread['body'] ?? ''),
            (string) ($thread['root_post_id'] ?? $thread['thread_id'] ?? $thread['post_id'] ?? '')
        );
    }
}
