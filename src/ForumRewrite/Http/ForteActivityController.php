<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

/**
 * Twenty-fourth/twenty-fifth Phase 2 slices (see
 * docs/plans/codebase_cleanup_audit_plan_v1.md and
 * docs/plans/activity_subsystem_extraction_plan_v1.md, "step 2"): the
 * page-shell route handlers for the Forte activity views, now that
 * ActivityService (the data layer) exists. Started with
 * /api/forte_commit_detail - the smallest of the four - per the sub-plan's
 * recommended order, then /forte/activity/ and /api/forte_activity_page
 * together (they share this same closure list) - needed zero new closures
 * beyond the three already here. Classic /activity is last (it alone
 * needs several more, unrelated to activity data itself).
 */
final class ForteActivityController
{
    /**
     * @param \Closure(): bool $commitsCapabilityAvailable
     * @param \Closure(): void $enqueueReadModelRecovery
     * @param \Closure(): void $sendReadModelCapabilityUnavailable
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $commitsCapabilityAvailable,
        private readonly \Closure $enqueueReadModelRecovery,
        private readonly \Closure $sendReadModelCapabilityUnavailable,
    ) {
    }

    /**
     * A commit's full file manifest is fetched on demand, not pre-rendered
     * for every loaded row the way activity items' detail articles are -
     * some commits touch thousands of files, and only one is ever viewed at
     * a time, so eagerly rendering all of them (as Stage 3's row list does)
     * would recreate the exact page-bloat problem the shared-manifest-block
     * mechanism was built to work around.
     *
     * @param array<string, mixed> $query
     */
    public function commitDetail(array $query): void
    {
        if (!($this->commitsCapabilityAvailable)()) {
            ($this->enqueueReadModelRecovery)();
            ($this->sendReadModelCapabilityUnavailable)();
            return;
        }

        $sha = (string) ($query['sha'] ?? '');
        if (preg_match('/^[0-9a-f]{40}$/', $sha) !== 1) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'invalid sha'], 400);
            return;
        }

        $activityService = $this->routeServices->activityService();
        $files = $activityService->activityCommitManifest($sha);
        if ($files === null) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'commit not found'], 404);
            return;
        }

        $html = $this->routeServices->renderFragment('partials/activity_commit_manifest.php', [
            'files' => $files,
            'commit_sha' => $sha,
            'commit_href' => $activityService->sourceCommitHref($sha) ?? '',
        ]);

        $this->routeServices->sendJson(['status' => 'ok', 'html' => $html], 200);
    }

    /**
     * Renders the Forte Activity three-pane view: a left pane of the same 5
     * category filters classic's `/activity/` offers, a list pane of items
     * for the selected filter, and a detail pane with the selected item's
     * full technical metadata.
     *
     * Calls `fetchActivity($view)` once per view (5 calls total, each
     * already its own cheap indexed/limited query - the same call classic's
     * own `renderActivity()` makes for whichever single view it's showing)
     * rather than deriving view membership from one superset fetch: a
     * view's own `LIMIT` window can reach further back in time than the
     * unfiltered "all" view's window once enough non-matching rows crowd
     * out its most recent items, so only a real per-view fetch reproduces
     * classic's exact per-view item set and counts.
     */
    public function board(
        string $requestedView = '',
        string $requestedSelected = '',
        string $requestedSort = '',
        string $requestedDirection = '',
    ): string {
        $activityService = $this->routeServices->activityService();
        $viewLabels = [
            'all' => 'All Activity',
            'content' => 'Visible Content',
            'identity' => 'Identity',
            'bootstrap' => 'Bootstraps',
            'approval' => 'Approvals',
        ];

        ['column' => $sortColumn, 'direction' => $sortDirection] = $activityService->resolveActivitySort($requestedSort, $requestedDirection);

        $itemsById = [];
        $viewItemIds = [];
        $viewPagination = [];
        foreach (array_keys($viewLabels) as $viewKey) {
            $viewItemIds[$viewKey] = [];
            $viewResult = $activityService->fetchActivity($viewKey, $sortColumn, $sortDirection);
            foreach ($viewResult['items'] as $item) {
                $itemId = (string) $item['id'];
                $viewItemIds[$viewKey][] = $itemId;
                if (!isset($itemsById[$itemId])) {
                    $item['forte_link'] = $this->activityItemBoardLink($item);
                    $itemsById[$itemId] = $item;
                }
            }

            // The cursor is derived from the last item actually returned for
            // this view, so an empty page never exposes a "Load more"
            // control with nothing to page from.
            $lastItem = $viewResult['items'][count($viewResult['items']) - 1] ?? null;
            $viewPagination[$viewKey] = [
                'has_more' => $viewResult['has_more'] && $lastItem !== null,
                'next_cursor' => $lastItem !== null ? [
                    'sort_value' => $activityService->activitySortValueFromItem($lastItem, $sortColumn),
                    'id' => (int) $lastItem['id'],
                ] : null,
            ];
        }

        foreach ($itemsById as $itemId => $item) {
            // $itemId comes back as an int here (PHP casts numeric string
            // array keys), so it must be re-stringified before a strict
            // in_array() against $viewItemIds' string ids.
            $itemIdString = (string) $itemId;
            foreach (array_keys($viewLabels) as $viewKey) {
                $itemsById[$itemId]['view_' . $viewKey] = in_array($itemIdString, $viewItemIds[$viewKey], true);
            }
        }

        // Matches each fetchActivity() call's own DB-level order (same sort
        // column, same id tiebreaker), so the merged cross-view pool's
        // display order agrees with any single view's own fetch order.
        $items = array_values($itemsById);
        usort($items, function (array $a, array $b) use ($activityService, $sortColumn, $sortDirection): int {
            $aValue = $activityService->activitySortValueFromItem($a, $sortColumn);
            $bValue = $activityService->activitySortValueFromItem($b, $sortColumn);
            $result = $sortDirection === 'desc' ? strcmp($bValue, $aValue) : strcmp($aValue, $bValue);
            if ($result !== 0) {
                return $result;
            }

            return $sortDirection === 'desc' ? ($b['id'] <=> $a['id']) : ($a['id'] <=> $b['id']);
        });

        $viewCounts = [];
        foreach ($viewLabels as $viewKey => $viewLabel) {
            $viewCounts[] = [
                'key' => $viewKey,
                'label' => $viewLabel,
                // Full total for the view (independent of pagination) - the
                // left-pane folder count. Separate from how many of those
                // are actually loaded/visible right now (below), which is
                // what the status bar tracks.
                'count' => $activityService->countActivityViewTotal($viewKey),
                'loadedCount' => count($viewItemIds[$viewKey]),
            ];
        }

        $commitsAvailable = ($this->commitsCapabilityAvailable)();
        if (!$commitsAvailable) {
            ($this->enqueueReadModelRecovery)();
        }

        // Commits are a parallel, structurally different row set - not
        // activity actions, so they're kept out of $itemsById/$items
        // rather than forced into that shape. Only `date` is sortable for
        // commits (resolveCommitSort() falls back to it for any other
        // requested column), so a Kind/Label header click while browsing
        // Commits has no effect on commit order - a deliberate Step 3 scope
        // decision, not a bug.
        $commitItems = [];
        if ($commitsAvailable) {
            ['column' => $commitSortColumn, 'direction' => $commitSortDirection] = $activityService->resolveCommitSort($requestedSort, $requestedDirection);
            $commitResult = $activityService->fetchCommits($commitSortColumn, $commitSortDirection);
            $commitItems = $commitResult['items'];
            $lastCommit = $commitItems[count($commitItems) - 1] ?? null;
            $viewPagination['commits'] = [
                'has_more' => $commitResult['has_more'] && $lastCommit !== null,
                'next_cursor' => $lastCommit !== null ? [
                    'sort_value' => $activityService->commitSortValueFromItem($lastCommit, $commitSortColumn),
                    'id' => (int) $lastCommit['id'],
                ] : null,
            ];
            $viewCounts[] = [
                'key' => 'commits',
                'label' => 'Commits',
                'count' => $activityService->countCommitsTotal(),
                'loadedCount' => count($commitItems),
            ];
        }

        $selectedView = $activityService->normalizeActivityView($requestedView);
        if (!$commitsAvailable && $selectedView === 'commits') {
            $selectedView = 'all';
        }
        // Commit selection is handled entirely client-side (Stage 4: fetched
        // on demand when a commit row is clicked, not pre-selected here) -
        // $viewItemIds has no 'commits' entry to look up against.
        $selectedItemId = $selectedView === 'commits'
            ? ''
            : (in_array($requestedSelected, $viewItemIds[$selectedView], true)
                ? $requestedSelected
                : (string) ($viewItemIds[$selectedView][0] ?? ''));
        $sortHeaderLinks = $this->activitySortHeaderLinks($selectedView, $sortColumn, $sortDirection);

        return $this->routeServices->renderStandalonePage(
            'forte_activity.php',
            [
                'items' => $items,
                'commitItems' => $commitItems,
                'viewCounts' => $viewCounts,
                'selectedView' => $selectedView,
                'selectedItemId' => $selectedItemId,
                'viewPagination' => $viewPagination,
                'sortHeaderLinks' => $sortHeaderLinks,
                'recoveryNotice' => $commitsAvailable ? '' : 'Commit history is temporarily unavailable while site data updates.',
            ],
            'Activity - Forte',
            'paned-reader-body',
            ['/assets/paned_activity_reader.js'],
            ['/assets/forte.css', '/assets/activity.css'],
        );
    }

    /**
     * Serves one additional page of activity rows for a single view, reusing
     * the canonical row partial (`paned_activity_item_row.php`) so appended
     * rows are byte-identical to the ones the initial page render produces.
     * Unlike `board()`, this only checks membership in the requested view -
     * an item's other `view_*` flags are left false, since paging one view
     * is not supposed to fetch the other 4 views' data too.
     *
     * Registered below `handle()`'s blanket non-GET rejection, so (like its
     * `/api/get_thread`, `/api/get_post`, and `/api/get_profile` siblings)
     * it never runs for a non-GET request and needs no method check here.
     *
     * @param array<string, mixed> $query
     */
    public function paginationPage(array $query): void
    {
        $activityService = $this->routeServices->activityService();
        $view = $activityService->normalizeActivityView((string) ($query['view'] ?? ''));

        $rawCursor = trim((string) ($query['cursor'] ?? ''));
        $cursor = null;
        if ($rawCursor !== '') {
            try {
                $decodedCursor = json_decode($rawCursor, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $decodedCursor = null;
            }

            if (
                !is_array($decodedCursor)
                || !isset($decodedCursor['sort_value'], $decodedCursor['id'])
                || !is_string($decodedCursor['sort_value'])
                || !is_int($decodedCursor['id'])
            ) {
                $this->routeServices->sendJson(['status' => 'error', 'error' => 'invalid cursor'], 400);
                return;
            }

            $cursor = [
                'sort_value' => $decodedCursor['sort_value'],
                'id' => $decodedCursor['id'],
            ];
        }

        if ($view === 'commits') {
            if (!($this->commitsCapabilityAvailable)()) {
                ($this->enqueueReadModelRecovery)();
                ($this->sendReadModelCapabilityUnavailable)();
                return;
            }

            // Commits are a parallel row set with their own fetch/sort
            // (Stage 2) and their own row partial (Stage 3) - and, unlike
            // activity items, no eagerly-rendered detail article: Stage 4
            // fetches a commit's full manifest on demand when it's
            // selected, not for every loaded row up front.
            ['column' => $commitSortColumn, 'direction' => $commitSortDirection] = $activityService->resolveCommitSort(
                (string) ($query['sort'] ?? ''),
                (string) ($query['dir'] ?? ''),
            );
            $commitResult = $activityService->fetchCommits($commitSortColumn, $commitSortDirection, $cursor);

            $html = '';
            foreach ($commitResult['items'] as $item) {
                $html .= $this->routeServices->renderFragment('partials/paned_activity_commit_row.php', [
                    'item' => $item,
                    'isSelected' => false,
                    'isTabStop' => false,
                    'visible' => true,
                ]);
            }

            $lastCommit = $commitResult['items'][count($commitResult['items']) - 1] ?? null;
            $hasMore = $commitResult['has_more'] && $lastCommit !== null;
            $nextCursor = $lastCommit !== null ? [
                'sort_value' => $activityService->commitSortValueFromItem($lastCommit, $commitSortColumn),
                'id' => (int) $lastCommit['id'],
            ] : null;

            $this->routeServices->sendJson([
                'status' => 'ok',
                'html' => $html,
                'detail_html' => '',
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
            ], 200);
            return;
        }

        ['column' => $sortColumn, 'direction' => $sortDirection] = $activityService->resolveActivitySort(
            (string) ($query['sort'] ?? ''),
            (string) ($query['dir'] ?? ''),
        );

        $result = $activityService->fetchActivity($view, $sortColumn, $sortDirection, $cursor);

        $html = '';
        $detailHtml = '';
        foreach ($result['items'] as $item) {
            $item['forte_link'] = $this->activityItemBoardLink($item);
            foreach (['all', 'content', 'identity', 'bootstrap', 'approval'] as $flagView) {
                $item['view_' . $flagView] = ($flagView === $view);
            }

            $html .= $this->routeServices->renderFragment('partials/paned_activity_item_row.php', [
                'item' => $item,
                'isSelected' => false,
                'isTabStop' => false,
                'visible' => true,
            ]);

            // Every appended row needs a matching detail-pane article, or
            // selecting it leaves the detail pane blank (no article matches
            // its id, so every existing article - and the placeholder - end
            // up hidden). Reuses the same partial the initial page render
            // uses, so this is never selected by default.
            $detailHtml .= $this->routeServices->renderFragment('partials/paned_activity_detail_article.php', [
                'item' => $item,
                'isSelected' => false,
            ]);
        }

        $lastItem = $result['items'][count($result['items']) - 1] ?? null;
        $hasMore = $result['has_more'] && $lastItem !== null;
        $nextCursor = $lastItem !== null ? [
            'sort_value' => $activityService->activitySortValueFromItem($lastItem, $sortColumn),
            'id' => (int) $lastItem['id'],
        ] : null;

        $this->routeServices->sendJson([
            'status' => 'ok',
            'html' => $html,
            'detail_html' => $detailHtml,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
        ], 200);
    }

    /**
     * Maps one activity item to its Forte-native destination link, mirroring
     * `activity.php`'s classic kind-based destinations but pointing
     * post/thread kinds at the existing `forte_post_permalink` URL shape
     * instead of classic's own `/threads/`/`/posts/` pages, so clicking
     * through from the Activity view lands in the Forte board itself.
     *
     * @param array<string, mixed> $item
     * @return array{href: string, label: string}
     */
    private function activityItemBoardLink(array $item): array
    {
        if ($item['kind'] === 'site_feature_flag') {
            return ['href' => '/tools/feature-flags/', 'label' => 'site feature flags'];
        }

        $threadId = (string) ($item['thread_id'] ?? '');
        $postId = $item['kind'] === 'thread_label_add' ? $threadId : (string) ($item['post_id'] ?? '');
        if ($threadId === '' || $postId === '') {
            return ['href' => '', 'label' => ''];
        }

        // Every resolvable item links into Forte itself, regardless of
        // board visibility - identity/bootstrap/approval-only threads are
        // excluded from the board's own listing but still resolve when
        // linked directly (ThreadRepository::byId(), kept out of the
        // list/tag groups/counts), so there's no more need for classic's
        // own /posts//threads/ destination as a fallback here.
        return [
            'href' => '/forte?selected=' . $threadId . '&created_post_id=' . $postId . '#post-' . $postId,
            'label' => $postId,
        ];
    }

    /**
     * Computes each sortable column header's `aria-sort` state and its
     * click target URL: the active column points at the *toggled*
     * direction, every other column points at its own default direction
     * (from `resolveActivitySort()`, so this never drifts out of sync with
     * the backend's own validation) - mirroring Board's `resolveForteBoardSort`
     * default-direction table, but resolved into links since Activity's
     * click behavior is a real navigation, not a client-side re-sort.
     *
     * @return array<string, array{ariaSort: string, href: string}>
     */
    private function activitySortHeaderLinks(string $view, string $activeColumn, string $activeDirection): array
    {
        $links = [];
        foreach (['kind', 'label', 'date'] as $column) {
            if ($column === $activeColumn) {
                $ariaSort = $activeDirection === 'desc' ? 'descending' : 'ascending';
                $targetDirection = $activeDirection === 'desc' ? 'asc' : 'desc';
            } else {
                $ariaSort = 'none';
                $targetDirection = $this->routeServices->activityService()->resolveActivitySort($column, '')['direction'];
            }

            $params = [];
            if ($view !== 'all') {
                $params['view'] = $view;
            }
            $params['sort'] = $column;
            $params['dir'] = $targetDirection;

            $links[$column] = [
                'ariaSort' => $ariaSort,
                'href' => '/forte/activity/?' . http_build_query($params),
            ];
        }

        return $links;
    }
}
