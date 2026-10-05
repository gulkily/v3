<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\ReadModel\ViewerTagLookup;
use ForumRewrite\SiteConfig;
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
    /**
     * Classic qdb.us paginates Latest/Top at 25 quotes per page rather than
     * dumping the whole unbounded board in one response (see
     * docs/plans/qdb_classic_urls_checklist.md); only these two activeSection
     * values have routing in Application.php that understands a page number,
     * so pagination is scoped to just those two, not every board() caller.
     */
    private const PAGE_SIZE = 25;
    private const PAGINATED_SECTIONS = ['latest', 'top'];

    /**
     * @param \Closure(): (array<string, mixed>|null) $resolveViewerProfileFromIdentityHint
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly string $repositoryRoot,
        private readonly \Closure $resolveViewerProfileFromIdentityHint,
    ) {
    }

    public function board(string $view, string $sort, string $activeSection = 'board', int $page = 1): string
    {
        $view = BoardViewOptions::normalizeView($view);
        $sort = BoardViewOptions::normalizeSort($sort);
        $viewOptions = BoardViewOptions::viewOptions($view, $sort);
        $sortOptions = BoardViewOptions::sortOptions($view, $sort);
        $threads = $this->fetchBoardThreads($view, $sort);
        $isQdbInstance = SiteConfig::siteName() === 'qdb';

        $pagination = null;
        if ($isQdbInstance && in_array($activeSection, self::PAGINATED_SECTIONS, true)) {
            $totalPages = max(1, (int) ceil(count($threads) / self::PAGE_SIZE));
            $page = max(1, min($page, $totalPages));
            $threads = array_slice($threads, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);
            $pagination = BoardViewOptions::pagination('/' . $activeSection, $page, $totalPages);
        }

        $viewerReactionState = $this->viewerReactionStateForThreads($threads, $isQdbInstance);
        $qdbQuoteCount = $isQdbInstance ? count(ThreadRepository::fetchThreads($this->routeServices->pdo())) : 0;

        return $this->routeServices->renderPageTemplate(
            'board.php',
            [
                'threads' => $threads,
                'view' => $view,
                'sort' => $sort,
                'viewOptions' => $viewOptions,
                'sortOptions' => $sortOptions,
                'viewLabel' => BoardViewOptions::activeLabel($viewOptions, $view),
                'sortLabel' => BoardViewOptions::activeLabel($sortOptions, $sort),
                'isQdbInstance' => $isQdbInstance,
                'viewerUpvotedThreadIds' => $viewerReactionState['upvoted'],
                'viewerDownvotedThreadIds' => $viewerReactionState['downvoted'],
                'viewerFlaggedPostIds' => $viewerReactionState['flagged'],
                'qdbQuoteCount' => $qdbQuoteCount,
                'pagination' => $pagination,
            ],
            'Board',
            $activeSection,
            array_merge([
                '/assets/inline_reply_form.js',
                '/assets/lazy_compose_signing.js',
            ], $isQdbInstance ? ['/assets/thread_reactions.js'] : []),
        );
    }

    /**
     * QDB welcome page: the qdb profile's own "/", replacing the generic
     * board default-view render with a short intro + the real quote count
     * + links to the other classic pages.
     */
    public function welcome(): string
    {
        $threads = ThreadRepository::fetchThreads($this->routeServices->pdo());
        $recentThreads = array_slice($threads, 0, 5);

        return $this->routeServices->renderPageTemplate(
            'qdb_welcome.php',
            [
                'qdbQuoteCount' => count($threads),
                'recentThreads' => $recentThreads,
            ],
            'Welcome',
            'welcome',
        );
    }

    /**
     * QDB classic URL: /random, /?random. A fresh shuffled page of quotes
     * each time, not a redirect to a single one. Only meaningful for the
     * qdb site profile.
     */
    public function random(int $count = 10): string
    {
        $threads = ThreadRepository::fetchThreads($this->routeServices->pdo());
        shuffle($threads);
        $threads = array_slice($threads, 0, $count);
        $viewerReactionState = $this->viewerReactionStateForThreads($threads, true);

        return $this->routeServices->renderPageTemplate(
            'qdb_random.php',
            [
                'threads' => $threads,
                'viewerUpvotedThreadIds' => $viewerReactionState['upvoted'],
                'viewerDownvotedThreadIds' => $viewerReactionState['downvoted'],
                'viewerFlaggedPostIds' => $viewerReactionState['flagged'],
            ],
            'Random',
            'random',
            ['/assets/thread_reactions.js'],
        );
    }

    /**
     * QDB classic URL: /search, /?search(=term). Only meaningful for the
     * qdb site profile - other profiles have no public search page.
     */
    public function search(string $term): string
    {
        $term = trim($term);
        $threads = $term === '' ? [] : array_values(array_filter(
            ThreadRepository::fetchThreads($this->routeServices->pdo()),
            fn (array $thread): bool => stripos((string) $thread['root_post_body'], $term) !== false
        ));
        $viewerReactionState = $this->viewerReactionStateForThreads($threads, true);

        return $this->routeServices->renderPageTemplate(
            'qdb_search.php',
            [
                'threads' => $threads,
                'term' => $term,
                'viewerUpvotedThreadIds' => $viewerReactionState['upvoted'],
                'viewerDownvotedThreadIds' => $viewerReactionState['downvoted'],
                'viewerFlaggedPostIds' => $viewerReactionState['flagged'],
            ],
            'Search',
            'search',
            ['/assets/thread_reactions.js'],
        );
    }

    /**
     * @param array<int, array<string, mixed>> $threads
     * @return array{upvoted: array<string, true>, downvoted: array<string, true>, flagged: array<string, true>}
     */
    private function viewerReactionStateForThreads(array $threads, bool $isQdbInstance): array
    {
        $empty = ['upvoted' => [], 'downvoted' => [], 'flagged' => []];
        if (!$isQdbInstance) {
            return $empty;
        }

        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();
        if ($viewerProfile === null) {
            return $empty;
        }

        $viewerIdentityId = (string) $viewerProfile['identity_id'];
        $rootPostIds = array_column($threads, 'root_post_id');

        return [
            'upvoted' => ViewerTagLookup::threadTags($this->repositoryRoot, $rootPostIds, 'upvote', $viewerIdentityId),
            'downvoted' => ViewerTagLookup::threadTags($this->repositoryRoot, $rootPostIds, 'downvote', $viewerIdentityId),
            'flagged' => ViewerTagLookup::postTags($this->repositoryRoot, $rootPostIds, 'flag', $viewerIdentityId),
        ];
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
            'leetness' => $this->compareLeetness($left, $right),
            default => $this->compareNewest($left, $right),
        };
    }

    /**
     * QDB's "1337" sort: closest to a score of exactly 1337 first.
     *
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareLeetness(array $left, array $right): int
    {
        $leetnessCompare = abs(1337 - (int) $left['score_total']) <=> abs(1337 - (int) $right['score_total']);
        if ($leetnessCompare !== 0) {
            return $leetnessCompare;
        }

        return $this->compareNewest($left, $right);
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
