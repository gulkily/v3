# Forte Activity Sortable Columns — Step 3: Development Plan

## Stage 1
- Goal: Parameterize `fetchActivity()`'s ordering and keyset cursor by an explicit sort column/direction instead of the hardcoded `created_at DESC` order, with a simpler, unified cursor shape.
- Dependencies: none (first stage).
- Expected changes:
  - New `resolveActivitySort(string $column, string $direction): array{column: string, direction: string}` - validates the column against an allowlist (`date`, `kind`, `label`) and applies a default direction per column when direction is missing/invalid (`date` -> desc, `kind`/`label` -> asc), mirroring Board's `resolveForteBoardSort()` pattern.
  - New `activitySortSql(string $column): string` - maps a validated column key to its SQL column expression (`date` -> `activity.created_at`, `kind` -> `activity.kind`, `label` -> `activity.label`), alongside the existing `activityViewSql()`.
  - `fetchActivity(string $view, string $sortColumn, string $sortDirection, ?array $afterCursor = null): array` - `ORDER BY` becomes `<sort column> <direction>, activity.id <direction>`; cursor shape simplifies from today's 3-field `{created_at, post_id, id}` to a uniform 2-field `{sort_value, id}`, with the keyset `WHERE` comparison rebuilt generically off whichever column is active.
  - `countActivityViewTotal()` is unaffected (it doesn't order).
  - Existing 4 callers of `fetchActivity()` (`renderForteActivity()`, backup preview, classic `/activity/` route, RSS feed) updated to pass `'date'`/`'desc'` explicitly, preserving today's exact output.
- Verification approach: Manually re-check the classic `/activity/` page, RSS feed, and `/backup/` preview render identically to before this stage; manually page through `kind`/`label` sorted fetches and confirm no gaps/duplicates, the same style of check used for the original pagination cursor.
- Risks or open questions:
  - Dropping `post_id` as a middle tiebreaker (unifying to `<sort column>, id`) changes tie-break order among same-sort-value rows - acceptable since `id` alone already guarantees a unique, gapless order, but it's a deliberate minor behavior change worth flagging.
  - `kind`/`label` sorting has no supporting index, so it's a full sort scan - fine at this table's current size, not fine indefinitely.
- Canonical components/API contracts touched: `fetchActivity()`; new `resolveActivitySort()`/`activitySortSql()` (`Application.php`).

## Stage 2
- Goal: Thread the active sort column/direction through the full page render and the paging endpoint so both use the sort-aware `fetchActivity()` consistently.
- Dependencies: Stage 1.
- Expected changes:
  - `renderForteActivity(string $requestedView, string $requestedSelected, string $requestedSort, string $requestedDirection)` - reads `sort`/`dir` request query params (alongside the existing `view`/`selected`), resolves them via `resolveActivitySort()`, and passes them into each of the 5 `fetchActivity()` calls; `$viewPagination`'s cursors become sort-aware automatically as a result.
  - `/forte/activity/` route handler passes `$query['sort']`/`$query['dir']` through to `renderForteActivity()`.
  - `handleForteActivityPage(array $query)` reads the same `sort`/`dir` query params, resolves them the same way, and passes them to `fetchActivity()` for the requested page.
- Verification approach: Manually load `/forte/activity/?sort=kind&dir=asc` and `/api/forte_activity_page?view=all&sort=kind&dir=asc&cursor=` directly; confirm both return items in `kind` ascending order and the endpoint's page continues exactly where the initial page's `next_cursor` left off.
- Risks or open questions:
  - An invalid/unknown `sort`/`dir` must resolve to the same default in both places, or the page and the endpoint could silently disagree - covered by both routing through Stage 1's single `resolveActivitySort()`.
- Canonical components/API contracts touched: `renderForteActivity()`; `/forte/activity/` route; `/api/forte_activity_page` endpoint.

## Stage 3
- Goal: Turn the static Kind/Label/Date column headers into Board-style sortable header buttons, server-rendered with the correct target sort/direction and `aria-sort` state.
- Dependencies: Stage 2 (needs the resolved current sort/direction to render `aria-sort` and compute each header's next-click target).
- Expected changes:
  - `paned_activity_item_list.php`'s `.paned-list-head` spans become Board's sortable-header shape: `data-paned-sort-head`, one `<span aria-sort="...">` per sortable column wrapping a `<button class="paned-sort-button" data-paned-sort-column="...">`, reusing the existing `forte.css` rules (`.paned-sort-button`, `[aria-sort] .paned-sort-button::after`) with no new CSS.
  - Each button carries a server-computed target URL via a new `data-paned-sort-href` attribute (current `view`, that column, and its toggled/default direction), using Stage 1's `resolveActivitySort()` default-direction table so toggle behavior matches the backend's own validation.
  - `renderForteActivity()`/`forte_activity.php` pass the resolved current `sort`/`dir` down to this partial.
- Verification approach: Manually load the page unsorted, with `sort=date&dir=desc` (default), and with `sort=kind&dir=asc`; confirm the right header shows `aria-sort` and each header's target href toggles direction correctly when that header is already active.
- Risks or open questions: none beyond what Stage 2 already covers.
- Canonical components/API contracts touched: `paned_activity_item_list.php`; reuses Board's `paned_board_thread_list.php` header pattern and `forte.css`'s existing `.paned-sort-button` rules.

## Stage 4
- Goal: Wire the sort headers to navigate on click, and make "Load more" continue in the active sort order.
- Dependencies: Stage 3 (header markup/hrefs); Stage 2 (endpoint accepts sort params).
- Expected changes:
  - `paned_activity_reader.js`: new click handler on the sort-header buttons that navigates via `window.location.href` to the button's `data-paned-sort-href` - a real page load, matching the "reset to page 1" decision, so no client-side row/cursor reconciliation is needed.
  - The existing "Load more" fetch call adds `&sort=<col>&dir=<dir>`, read from the current page's URL via new `currentSortFromUrl()`/`currentDirectionFromUrl()` helpers mirroring the existing `currentViewFromUrl()` pattern.
- Verification approach: Manually click a header, confirm the page reloads sorted correctly with the URL updated; click "Load more" under a non-default sort and confirm appended rows continue in that same order with no gaps/duplicates.
- Risks or open questions:
  - The navigated-to URL preserves `view` but intentionally drops `selected`, starting selection fresh after a re-sort - consistent with "reset to the top of the list," not an oversight.
- Canonical components/API contracts touched: `paned_activity_reader.js`; consumes `/api/forte_activity_page`'s `sort`/`dir` params from Stage 2.
