<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\PresentationSlotRegistry;
use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\ReadModel\ViewerTagLookup;
use ForumRewrite\Qdb\QdbBoardPolicy;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
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
     * @param \Closure(): (array<string, mixed>|null) $resolveViewerProfileFromIdentityHint
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly string $repositoryRoot,
        private readonly \Closure $resolveViewerProfileFromIdentityHint,
    ) {
    }

    /** Threads per board list page; pinned threads come on the first page in addition. */
    private const LIST_PAGE_SIZE = 50;

    public function board(string $view, string $sort, string $activeSection = 'board', int $page = 1, ?QdbBoardPolicy $qdbPolicy = null, mixed $listPage = null): string
    {
        $view = BoardViewOptions::normalizeView($view);
        $sort = BoardViewOptions::normalizeSort($sort);
        $viewOptions = BoardViewOptions::viewOptions($view, $sort);
        $sortOptions = BoardViewOptions::sortOptions($view, $sort);
        if ($qdbPolicy !== null && $view === 'all' && in_array($activeSection, ['latest', 'top', 'leetness'], true)
            && $sort === ($activeSection === 'latest' ? 'newest' : $activeSection)) {
            $selected = \ForumRewrite\ReadModel\QdbPage::page($this->routeServices->pdo(), $activeSection, $page);
            $threads = $selected['threads'];
            $qdbQuoteCount = $selected['count'];
            $pagination = $qdbPolicy->pagination($selected['count'], $selected['page'], '/' . $activeSection);
        } else {
            $threads = $this->fetchBoardThreads($view, $sort, $qdbPolicy);
            $qdbQuoteCount = $qdbPolicy?->quoteCount($threads) ?? 0;
            $qdbPresentation = $qdbPolicy?->paginate($threads, $activeSection, $page);
            $threads = $qdbPresentation['threads'] ?? $threads;
            $pagination = $qdbPresentation['pagination'] ?? null;
        }
        $viewerReactionState = $qdbPolicy?->viewerReactionState($threads) ?? ['voted' => [], 'flagged' => []];
        $voteCaptionPair = $qdbPolicy?->selectCaptionPair();
        $qdbVoteTags = $qdbPolicy?->voteTags() ?? [];
        $boardCardSlot = PresentationSlotRegistry::resolve(\ForumRewrite\SiteProfileRegistry::active(), 'boardCard');
        if ($boardCardSlot === 'list' && $qdbPolicy === null) {
            return $this->boardList($view, $sort, $threads, $viewOptions, $sortOptions, \ForumRewrite\Pagination\PageNavigation::pageNumber($listPage));
        }

        return $this->routeServices->renderPageTemplate(
            'board.php',
            [
                'listingQuery' => $qdbPolicy === null ? \ForumRewrite\Tools\BoardListingQuery::context($view, $sort) : null,
                'threads' => $threads,
                'view' => $view,
                'sort' => $sort,
                'viewOptions' => $viewOptions,
                'sortOptions' => $sortOptions,
                'viewLabel' => BoardViewOptions::activeLabel($viewOptions, $view),
                'sortLabel' => BoardViewOptions::activeLabel($sortOptions, $sort),
                'isQdbInstance' => $qdbPolicy !== null,
                'boardCardPartial' => $boardCardSlot === 'quote' ? 'partials/quote_card.php' : 'partials/thread_card.php',
                'boardCardData' => $boardCardSlot === 'quote' ? [
                    'viewerVotedThreadIds' => $viewerReactionState['voted'],
                    'viewerFlaggedPostIds' => $viewerReactionState['flagged'],
                    'voteCaptionPair' => $voteCaptionPair,
                    'qdbVoteTags' => $qdbVoteTags,
                ] : ['showPinnedMarker' => true],
                'boardFooterPartial' => $qdbPolicy === null ? null : 'partials/qdb_footer.php',
                'viewerVotedThreadIds' => $viewerReactionState['voted'],
                'viewerFlaggedPostIds' => $viewerReactionState['flagged'],
                'qdbVoteTags' => $qdbVoteTags,
                'qdbQuoteCount' => $qdbQuoteCount,
                'pagination' => $pagination,
            ],
            $qdbPolicy !== null ? match ($activeSection) {
                'latest' => 'Latest Quotes',
                'top' => 'Top Quotes',
                'leetness' => '1337 Quotes',
                default => 'Quotes',
            } : 'Board',
            $activeSection,
            array_merge([
                '/assets/inline_reply_form.js',
                '/assets/lazy_compose_signing.js',
            ], $qdbPolicy !== null ? ['/assets/thread_reactions.js'] : []),
        );
    }

    /**
     * The `list` board: pinned threads grouped above the latest ones on page 1;
     * the latest ones are paged by number with the shared native links.
     *
     * @param array<int, array<string, mixed>> $threads Sorted, pinned first.
     * @param array<int, array{label:string,href:string,is_active:bool,key:string}> $viewOptions
     * @param array<int, array{label:string,href:string,is_active:bool,key:string}> $sortOptions
     */
    private function boardList(string $view, string $sort, array $threads, array $viewOptions, array $sortOptions, int $number): string
    {
        $pinned = array_values(array_filter($threads, fn (array $thread): bool => $this->isPinned($thread)));
        $latest = array_values(array_filter($threads, fn (array $thread): bool => !$this->isPinned($thread)));
        $items = array_slice($latest, ($number - 1) * self::LIST_PAGE_SIZE, self::LIST_PAGE_SIZE);
        $hasNext = $number * self::LIST_PAGE_SIZE < count($latest);
        $pagination = \ForumRewrite\Pagination\PageNavigation::numbered('/threads/', self::listState($view, $sort), $number, $hasNext, $items === []);
        $pinned = $number === 1 ? $pinned : [];
        $imagesEnabled = $this->routeServices->featureFlags()->isEnabled(FeatureFlagRegistry::LIST_THUMBNAILS_ENABLED);
        $pinned = \ForumRewrite\Images\ListThumbnails::attach($this->routeServices->pdo(), $this->repositoryRoot, $imagesEnabled, $pinned, 'root_post_id', true);
        $items = \ForumRewrite\Images\ListThumbnails::attach($this->routeServices->pdo(), $this->repositoryRoot, $imagesEnabled, $items, 'root_post_id', true);

        return $this->routeServices->renderPageTemplate(
            'board_list.php',
            [
                'listingQuery' => \ForumRewrite\Tools\BoardListingQuery::context($view, $sort),
                'view' => $view,
                'sort' => $sort,
                'viewOptions' => $viewOptions,
                'sortOptions' => $sortOptions,
                'sortLabel' => BoardViewOptions::activeLabel($sortOptions, $sort),
                'pinnedThreads' => $pinned,
                'threads' => $items,
                'pagination' => $pagination,
                'paginationError' => null,
            ],
            'Board',
            'board',
            ['/assets/inline_reply_form.js', '/assets/lazy_compose_signing.js'],
        );
    }

    /**
     * Where a board `with` link lands: the list page holding that thread, or
     * page 1 for a pinned thread. Null for QDB boards, card layouts and
     * threads outside this view.
     */
    public function listWithLocation(string $view, string $sort, mixed $with): ?string
    {
        $id = \ForumRewrite\Pagination\PageNavigation::withId($with);
        if ($id === null || PresentationSlotRegistry::resolve(\ForumRewrite\SiteProfileRegistry::active(), 'boardCard') !== 'list') return null;
        $view = BoardViewOptions::normalizeView($view);
        $sort = BoardViewOptions::normalizeSort($sort);
        $threads = $this->fetchBoardThreads($view, $sort, null);
        $latest = array_values(array_filter($threads, fn (array $thread): bool => !$this->isPinned($thread)));
        $index = array_search($id, array_map(static fn (array $thread): string => (string) $thread['root_post_id'], $latest), true);
        if ($index === false) {
            $pinned = in_array($id, array_map(static fn (array $thread): string => (string) $thread['root_post_id'], $threads), true);
            if (!$pinned) return null;
            $index = 0;
        }
        return \ForumRewrite\Pagination\PageNavigation::withLocation('/threads/', self::listState($view, $sort), intdiv($index, self::LIST_PAGE_SIZE) + 1, $id);
    }

    /** @return array<string,string> View and sort for links, leaving out the defaults (liked, newest). */
    private static function listState(string $view, string $sort): array
    {
        $state = [];
        if ($view !== 'liked') $state['view'] = $view;
        if ($sort !== 'newest') $state['sort'] = $sort;
        return $state;
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
                'viewerVotedThreadIds' => $viewerReactionState['voted'],
                'viewerFlaggedPostIds' => $viewerReactionState['flagged'],
                'qdbVoteTags' => ['upvote', 'downvote'],
            ],
            'Random',
            'random',
            ['/assets/lazy_compose_signing.js', '/assets/thread_reactions.js'],
        );
    }

    /**
     * Board search (`/search?q=`) over top-level threads for non-QDB
     * profiles; QDB keeps its own classic search in QdbExperience.
     */
    public function search(mixed $term, mixed $position): string
    {
        $term = is_string($term) ? trim($term) : '';
        $context = \ForumRewrite\ReadModel\ThreadSearchPage::context($term);
        $error = null;
        try {
            if ($position !== null && !is_string($position)) throw new \InvalidArgumentException('This page position is invalid.');
            $cursor = $position === null ? null : \ForumRewrite\Pagination\PageNavigation::decode($position, $context);
            $page = \ForumRewrite\ReadModel\ThreadSearchPage::page($this->routeServices->pdo(), $term, $cursor['boundary'] ?? null, $cursor['direction'] ?? 'next');
        } catch (\InvalidArgumentException $exception) {
            $error = $exception->getMessage();
            $page = ['items' => [], 'previous' => null, 'next' => null];
        }
        $pagination = \ForumRewrite\Pagination\PageNavigation::links('/search', $term === '' ? [] : ['q' => $term], $context,
            $page['previous'], $page['next'], $error !== null || ($position !== null && $page['items'] === []));

        return $this->routeServices->renderPageTemplate(
            'board_search.php',
            [
                'term' => $term,
                'threads' => $page['items'],
                'pagination' => $pagination,
                'paginationError' => $error,
            ],
            $term === '' ? 'Search' : $term . ' - Search',
            'search',
        );
    }

    /**
     * @param array<int, array<string, mixed>> $threads
     * @return array{voted: array<string, true>, flagged: array<string, true>}
     */
    private function viewerReactionStateForThreads(array $threads, bool $isQdbInstance): array
    {
        $empty = ['voted' => [], 'flagged' => []];
        if (!$isQdbInstance) {
            return $empty;
        }

        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();
        if ($viewerProfile === null) {
            return $empty;
        }

        $viewerIdentityId = (string) $viewerProfile['identity_id'];
        $rootPostIds = array_column($threads, 'root_post_id');

        $pdo = $this->routeServices->pdo();
        $voted = ViewerTagLookup::threadTags($pdo, $rootPostIds, 'upvote', $viewerIdentityId);
        $voted += ViewerTagLookup::threadTags($pdo, $rootPostIds, 'downvote', $viewerIdentityId);

        return [
            'voted' => $voted,
            'flagged' => ViewerTagLookup::postTags($pdo, $rootPostIds, 'flag', $viewerIdentityId),
        ];
    }

    public function rss(?QdbBoardPolicy $qdbPolicy = null): string
    {
        $items = [];
        $threads = ThreadRepository::fetchThreads($this->routeServices->pdo());
        if ($qdbPolicy !== null) {
            $threads = $qdbPolicy->eligibleQuotes($threads);
        }
        foreach ($threads as $thread) {
            $title = $this->displayThreadTitle($thread);
            $items[] = RssFeed::item($title, '/threads/' . $thread['root_post_id'], $thread['body_preview'], (string) $thread['last_activity_at']);
        }

        return RssFeed::feed('Board', '/?format=rss', $items);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchBoardThreads(string $view, string $sort, ?QdbBoardPolicy $qdbPolicy): array
    {
        $threads = array_values(array_filter(
            ThreadRepository::fetchThreads($this->routeServices->pdo()),
            fn (array $thread): bool => $this->matchesView($thread, $view)
        ));
        if ($qdbPolicy !== null) {
            $threads = $qdbPolicy->eligibleQuotes($threads);
        }

        usort($threads, fn (array $left, array $right): int => $this->compareThreads($left, $right, $sort, $qdbPolicy));

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
    private function compareThreads(array $left, array $right, string $sort, ?QdbBoardPolicy $qdbPolicy): int
    {
        $pinnedCompare = $this->comparePinnedStatus($left, $right);
        if ($pinnedCompare !== 0) {
            return $pinnedCompare;
        }

        if ($sort === 'leetness' && $qdbPolicy !== null) {
            return $qdbPolicy->compareLeetness($left, $right, $this->compareNewest($left, $right));
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
        if (($thread['retracted_at'] ?? null) !== null) {
            return \ForumRewrite\Support\RetractedPostNotice::TITLE;
        }

        return ThreadTitle::displayTitle(
            (string) ($thread['subject'] ?? ''),
            \ForumRewrite\Support\PostText::plainPreview((string) ($thread['body_preview'] ?? $thread['body'] ?? ''), $thread['body_format'] ?? null),
            (string) ($thread['root_post_id'] ?? $thread['thread_id'] ?? $thread['post_id'] ?? ''),
            80,
            $this->routeServices->featureFlags()->isEnabled(FeatureFlagRegistry::MEDIA_EMBEDS_ENABLED)
        );
    }
}
