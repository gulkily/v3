# Forte Activity Sortable Columns — Step 4: Implementation Summary

## Stage 1 - Sort-aware `fetchActivity()` and unified cursor
- Changes:
  - New `resolveActivitySort(string $requestedColumn, string $requestedDirection): array{column, direction}` (`Application.php`) - validates against `date`/`kind`/`label`, falls back to `date` and its per-column default direction (`date` desc, `kind`/`label` asc), mirroring `resolveForteBoardSort()`'s pattern. Unlike Board, always resolves to a real column (never an empty-string sentinel), since the cursor needs one.
  - New `activitySortSql(string $column): string` - maps a validated column to its SQL expression (`kind`→`activity.kind`, `label`→`activity.label`, default→`activity.created_at`), alongside the existing `activityViewSql()`.
  - New `activitySortValueFromItem(array $item, string $column): string` - the inverse mapping (column → item field), used by callers to build a `next_cursor` from the last returned item without duplicating the column mapping.
  - `fetchActivity(string $view, string $sortColumn, string $sortDirection, ?array $afterCursor = null): array` - `ORDER BY` is now `<sort column> <direction>, activity.id <direction>`; the cursor shape simplified from the old 3-field `{created_at, post_id, id}` to a uniform 2-field `{sort_value, id}`, with the keyset `WHERE` built generically off whichever column is active.
  - All 5 existing call sites (`renderForteActivity()`, `handleForteActivityPage()`, backup preview, classic `/activity/` route, RSS feed) updated to the new signature/cursor shape; all 5 pass `'date', 'desc'` explicitly for now (Stage 2 threads real request-derived sort into the two that need it).
  - `handleForteActivityPage()`'s cursor JSON decode/validation and `next_cursor` construction, and `renderForteActivity()`'s `$viewPagination[...]['next_cursor']` construction, both updated to the new `{sort_value, id}` shape via `activitySortValueFromItem()`.
- Verification:
  - `php -l src/ForumRewrite/Application.php` - no syntax errors.
  - `php scripts/rebuild_read_model.php` then started a local dev server; confirmed the 3 unaffected callers still work: classic `/activity/?view=content` (200), RSS feed (`/activity/?format=rss`, still exactly 100 `<item>`s), `/backup/` (200) - unchanged output for callers Stage 1 didn't touch.
  - `/api/forte_activity_page` end-to-end with the new cursor shape: page 1 → 100 rows, `next_cursor: {sort_value, id}`; page 2 via that cursor → 100 more rows, zero id overlap, page2's first id immediately follows page1's last id - same no-gap/no-duplicate check used for the original date-order pagination.
  - Wrote a one-off PHP script (reflection-invoking `fetchActivity()` directly, since Stage 2 hasn't wired `sort`/`dir` query params through yet) covering `kind asc`, `label asc`, `label desc`, and `date desc`: for each, verified page 1's 100 items are correctly ordered by the active column (then `id`), and that page 2 (fetched via the derived cursor) continues immediately after page 1 with zero id overlap - all four passed.
  - Server log: only the pre-existing, unrelated `sendHtml()` header-timing warning already noted in the pagination feature's own summary (same root cause, just a shifted line number since the file grew) - nothing new.
- Notes:
  - Dropping `post_id` as a middle tiebreaker (now just `<sort column>, id`) is a deliberate, minor behavior change flagged in the Step 3 plan: `id` alone already guarantees a unique, gapless order, so pagination correctness is unaffected; only the tie-break order among rows sharing the exact same sort value can shift slightly from before.
  - `kind`/`label` sorting has no supporting index (only `date`/`post_id`/`id` do, via `activity_recent_idx`), so it's a full sort scan - acceptable at this table's current size (~1600 rows), flagged as a future concern if the table grows substantially.
  - Nothing in the app actually sends `sort`/`dir` yet - Stage 2 threads real request-derived values through the two callers that need it.

## Stage 2 - Thread sort/dir through the page render and the paging endpoint
- Changes:
  - `/forte/activity/` route now reads `$query['sort']`/`$query['dir']` and passes them to `renderForteActivity()`.
  - `renderForteActivity()` gained `$requestedSort`/`$requestedDirection` params, resolves them once via `resolveActivitySort()`, and passes the resolved column/direction into all 5 `fetchActivity()` calls (replacing Stage 1's temporary hardcoded `'date', 'desc'`) and into `activitySortValueFromItem()` when building each view's `next_cursor`.
  - Found and fixed a gap Stage 1 didn't cover: after merging all 5 views' items, `renderForteActivity()` re-sorts the merged pool with its own `usort()` for display - that comparator was still hardcoded to `created_at DESC, post_id DESC, id DESC` regardless of the active sort, so the actual on-screen order would have stayed stuck on date-desc even with sort-aware fetches underneath. Rewrote it to compare via `activitySortValueFromItem($item, $sortColumn)` with direction-aware `strcmp()`/`<=>`, matching each per-view fetch's own DB-level order (same column, same `id` tiebreaker) so the merged display order is consistent with it.
  - `handleForteActivityPage()` reads `$query['sort']`/`$query['dir']`, resolves them the same way, and passes them to `fetchActivity()` and into `activitySortValueFromItem()` for `next_cursor` (replacing Stage 1's temporary hardcoded values).
- Verification:
  - `php -l` - no syntax errors.
  - `GET /forte/activity/?sort=kind&dir=asc`: extracted all 300 rendered rows' Kind column text and confirmed it's non-decreasing (`kinds == sorted(kinds)`) - the merged, multi-view pool actually displays in the requested order, not just each individual fetch.
  - `GET /api/forte_activity_page?view=all&sort=kind&dir=asc&cursor=`: returns the identical `next_cursor` the full page's own `all`-view "Load more" button carries - confirms the two code paths (full render vs. endpoint) resolve sort identically. Fetched page 2 with that cursor and diffed its ids against the full page's 100 `all`-flagged rows: zero overlap, and the sort value correctly progressed alphabetically (`approval` → `identity_bootstrap`).
  - Regression: default (`/forte/activity/`, no `sort`/`dir`), classic `/activity/?format=rss` (still exactly 100 items), `/backup/` all unaffected.
  - Fallback consistency: an invalid `sort=bogus&dir=bogus` request and a request with no `sort`/`dir` at all both resolve to the exact same `date`/`desc` cursor value - confirms `resolveActivitySort()` is the single source of truth in both places, per Stage 2's own risk note.
  - Server log clean (only the same pre-existing, unrelated header-timing warning already on file).
- Notes:
  - The merged-pool `usort()` fix wasn't explicitly called out in the Step 3 plan (which only mentioned the per-view `fetchActivity()` calls), but it's squarely inside Stage 2's actual goal - "so both use the sort-aware fetchActivity() consistently" - and without it the feature would visibly not work, so it's a correction within Stage 2's own scope, not new scope.

## Stage 3 - Sortable header UI
- Changes:
  - New `activitySortHeaderLinks(string $view, string $activeColumn, string $activeDirection): array<string, array{ariaSort: string, href: string}>` (`Application.php`) - for each of the 3 sortable columns, computes its `aria-sort` state and a target URL: the active column's link toggles direction, every other column's link points at its own default direction via `resolveActivitySort($column, '')`, so header toggle behavior can never drift out of sync with the backend's own validation. Links always carry `view` (when not `all`) and explicit `sort`/`dir`, and intentionally omit `selected`, matching the "reset to the top" decision from Step 1.
  - `renderForteActivity()` calls this once (`$selectedView`, `$sortColumn`, `$sortDirection`) and passes `sortHeaderLinks` down through `forte_activity.php` into `paned_activity_item_list.php`.
  - `paned_activity_item_list.php`'s static `.paned-list-head` spans became Board's exact sortable-header shape (`data-paned-sort-head`, `<span aria-sort>` wrapping `<button class="paned-sort-button" data-paned-sort-column="...">`), reusing `forte.css`'s existing `.paned-sort-button`/`[aria-sort]` rules with no new CSS. Each button additionally carries `data-paned-sort-href` (Board's buttons don't need this, since Board sorts client-side; Activity's click is a real navigation).
  - Drive-by fix: `paned_activity_item_list.php`'s and `forte_activity.php`'s `@var` docblocks for `$viewPagination`'s cursor shape were still describing the pre-Stage-1 `{created_at, post_id, id}` shape; corrected to `{sort_value, id}`.
- Verification:
  - `php -l` on all 3 changed files - no syntax errors.
  - Loaded the page with no `sort`/`dir` and with explicit `sort=date&dir=desc`: identical header markup in both (`Date` shows `aria-sort="descending"`, `Kind`/`Label` show `aria-sort="none"`) - confirms the default resolves the same way whether implicit or explicit, consistent with Stage 2's own fallback check.
  - `sort=kind&dir=asc` → `Kind` shows `ascending` with an href toggling to `dir=desc`; `sort=kind&dir=desc` → shows `descending` toggling back to `asc`.
  - `sort=label&dir=asc&view=approval` → all 3 header hrefs carry `view=approval`, `Label`'s href toggles to `desc`, `Kind`/`Date` point at their own defaults (`asc`/`desc` respectively) - confirms view is preserved and per-column defaults are correct even when a different column is active.
  - Server log clean.
- Notes:
  - Clicking a header does nothing yet (no `href` navigation is wired to the button's `data-paned-sort-href` - buttons don't navigate on their own). Stage 4 adds that plus sort-aware "Load more".
