# Forte Activity Pagination — Step 4: Implementation Summary

## Stage 1 - Cursor-aware `fetchActivity()`
- Changes:
  - `fetchActivity(string $view, ?array $afterCursor = null): array` (`src/ForumRewrite/Application.php`) now accepts an optional keyset cursor `{created_at, post_id, id}` and returns `{items, has_more}` instead of a bare item array.
    - Cursor filtering added as an extra `WHERE` fragment matching the existing `ORDER BY created_at DESC, post_id DESC, id DESC`, using `COALESCE(post_id, -1)` so a `NULL post_id` (from the `LEFT JOIN posts`) sorts consistently with SQLite's own NULL-last-in-DESC behavior.
    - Fetches `ACTIVITY_ITEM_LIMIT + 1` rows and trims the extra one back off to derive `has_more`, before the existing per-view PHP-level filter runs (so `has_more` reflects the real next SQL row, independent of that filter).
  - All 4 call sites updated to read `['items']` from the new return shape (the plan named 3; `renderForteActivity()`'s own call needed the same update to keep compiling, so it was included too — no other scope added):
    - `renderForteActivity()` (`Application.php:1042`)
    - backup preview (`Application.php:1587`)
    - classic `/activity/` route (`Application.php:1643`)
    - RSS feed `renderActivityRss()` (`Application.php:2162`)
- Verification:
  - `php -l src/ForumRewrite/Application.php` — no syntax errors.
  - `php scripts/rebuild_read_model.php` — rebuilt read model (1629 activity rows, well over the 100-item cap, for pagination-relevant testing later).
  - Started a local dev server (`./v3 start 127.0.0.1:8010`) and manually hit all 4 call sites:
    - `GET /activity/?view=content` → 200 (classic route, dynamic PHP path).
    - `GET /activity/?format=rss` → 200, exactly 100 `<item>` entries (unchanged cap).
    - `GET /forte/activity/` → 200, renders the 3-pane page with a 300-item merged pool and all 5 `data-paned-activity-view` filter rows present.
    - `GET /backup/` → 200 (backup preview route).
  - Server log showed no fatal errors from the change; one pre-existing, unrelated `headers already sent` warning in `sendHtml()` appears on both requests and predates this change (not touched by Stage 1).
- Notes:
  - No behavior change for existing callers: first-page semantics (no cursor) are identical, just wrapped in the new `{items, has_more}` shape.
  - `has_more`/cursor values aren't consumed anywhere yet — Stage 3 wires them into the initial page render and the "Load more" control.

## Stage 2 - Extract shared row-rendering partial
- Changes:
  - New `templates/partials/paned_activity_item_row.php` holds the single-item row markup (`data-paned-activity-id`, all 5 `data-paned-activity-view-*` flags, kind/label/date) previously inline in the list partial.
  - `templates/partials/paned_activity_item_list.php` now loops and calls the existing `$partial()` template helper (`TemplateRenderer::renderFile()`) per item instead of inlining the row markup, using the same `$partial('partials/...', [...])` + `$indent(...)` convention already used by `forte_activity.php` for its other partials.
  - Tab-stop bookkeeping (`$tabStopAssigned`, computed once per item) stays in the list partial's loop and is passed into the row partial as a plain `isTabStop` boolean, since that state can't live inside a per-row partial call.
- Verification:
  - `php -l` on both changed/added template files — no syntax errors.
  - Rendered `/forte/activity/` before and after the extraction (via `git stash`) and diffed the two HTML outputs: only whitespace differences around the row `<div>`'s closing `>` (an `$indent()` formatting side effect); all data attributes, classes, and text content are identical.
  - Checked the dev server log for the diffed requests — no new PHP warnings/errors introduced.
- Notes:
  - This partial is now the canonical single-row renderer; Stage 4's paging endpoint reuses it directly instead of duplicating row markup in a new place.

## Stage 3 - Per-view "Load more" control on initial page load
- Changes:
  - `renderForteActivity()` (`Application.php`) now captures `$viewPagination[$viewKey] = ['has_more' => bool, 'next_cursor' => {created_at, post_id, id}|null]` per view, derived from each view's `fetchActivity()` result. `has_more` is forced `false` when that view's page came back empty, so a control is never shown without a cursor to page from.
  - `viewPagination` is threaded through `renderForteActivity()` → `forte_activity.php` → `paned_activity_item_list.php`.
  - `paned_activity_item_list.php` renders one `<button data-paned-activity-load-more data-paned-activity-view="{key}" data-paned-activity-cursor="{json}">` per view inside a new `.paned-list-load-more-group`, `hidden` unless that view is both the currently selected view and has more items. The cursor is JSON-encoded and HTML-escaped so Stage 5's JS can read it back verbatim via `JSON.parse`.
  - Minimal styling added to `forte.css` (`.paned-list-load-more-group`, `.paned-list-load-more-button`) matching the existing toolbar-button look.
- Verification:
  - `php -l` on all changed PHP files — no syntax errors.
  - Loaded `/forte/activity/` (default `all` view): only the `all` button lacks `hidden`; the other 4 have it, each with a distinct, correctly-escaped cursor JSON matching that view's last loaded item.
  - Loaded `/forte/activity/?view=content`: only the `content` button lacks `hidden`, `all`'s button gained `hidden` — control visibility correctly follows `$selectedView` at render time.
  - Server log showed no new warnings/errors across these requests.
- Notes:
  - No client-side behavior changed yet (Stage 5 wires the click handler and swaps `hidden` on filter switch); clicking the button currently does nothing.

## Stage 4 - Paging endpoint
- Changes:
  - New route `GET /api/forte_activity_page` (`Application.php`), registered alongside the other read-only `/api/get_*` endpoints (after `handle()`'s blanket non-GET rejection, so — like those siblings — it needs no method check of its own).
  - `handleForteActivityPage(array $query)`: takes `view` and an optional JSON `cursor` query param, validates the cursor shape (`{created_at: string, post_id: string|null, id: int}`) and returns 400 on malformed input.
  - Calls `fetchActivity($view, $cursor)`, renders each returned item through the Stage 2 canonical row partial via `TemplateRenderer::renderFragment()` (built for exactly this "HTML fragment for client-side JS" case), and returns JSON: `{status: 'ok', html, has_more, next_cursor}`.
  - Per the plan, appended items only get the requesting view's `view_*` flag set true; the other 4 are set false rather than rechecked, so paging one view never re-queries the other 4.
  - No new access-control code added: the endpoint sits after the same blanket members-only gate as every other route, so it is exactly as reachable/unreachable as `/forte/activity/` itself under `FORUM_APPROVED_MEMBERS_ONLY` (neither path is in `isApplicationRoute()`, so both 404 rather than 403 for an unapproved viewer under that mode — a pre-existing gap outside this feature's scope, not something newly introduced here).
- Verification:
  - `php -l src/ForumRewrite/Application.php` — no syntax errors.
  - `GET /api/forte_activity_page?view=all` (empty cursor) then again with the response's `next_cursor` as the next request's cursor: 100 + 100 rows, zero id overlap between the two pages, and the second page's first item immediately follows the first page's last item (id 312 → 232) — confirms no gaps/duplicates in the keyset pagination.
  - Paged the smaller `approval` view to exhaustion: page 1 returns 100 rows with `has_more: true`, page 2 returns the remaining 5 with `has_more: false`, 105 unique ids total, zero overlap.
  - Error paths: malformed `cursor` JSON → `400 {"status":"error","error":"invalid cursor"}`; `POST` to the endpoint → `405` (from `handle()`'s existing blanket non-GET rejection, confirming no bypass).
  - Server log clean across all of the above (no new PHP warnings/errors).
- Notes:
  - Response rows are visually identical to Stage 2's row partial output by construction (same partial, same call convention) — no separate markup to drift out of sync.
  - Nothing consumes this endpoint client-side yet; Stage 5 wires the "Load more" button to call it.

## Stage 5 - Client-side Load more wiring
- Changes:
  - `templates/partials/paned_activity_item_list.php`: added a `data-paned-activity-has-more` attribute to each Load More button (Stage 3 only rendered the initial `hidden` state, conflating "not the current view" with "exhausted"; JS needs the two tracked separately across filter switches).
  - `public/assets/paned_activity_reader.js`:
    - New `updateLoadMoreButtonVisibility(view)` helper: a button is visible only when it's both the current view and still has more, called from `selectFilter()` so filter switches keep every button's visibility correct without duplicating that logic.
    - New click handler on the load-more button group: reads the clicked button's `view`/`cursor` attributes, disables it with a "Loading…" label, calls `GET /api/forte_activity_page`, and on success appends the returned HTML to the list body, refreshes the `rows` array (so Prev/Next and filter switching see the new rows), updates the button's cursor/has-more attributes, and calls `selectFilter(currentViewFromUrl())` to recompute visible count, status text, and button visibility in one pass (reusing existing logic instead of duplicating it). On failure, re-enables the button and restores its label.
- Verification (real headless-Chrome, driven over the DevTools Protocol since no browser-automation tool was available in this environment - a raw CDP script was used to navigate, click, and inspect the live DOM, plus check `console` for errors):
  - Clicking "Load more" on the `all` view: rows grew 300 → 400 in the DOM (100 new rows appended), the button's cursor updated to the second page's `next_cursor`, status text updated from "100 items" to "200 items", `console` had zero messages/exceptions. Screenshot confirms the button renders correctly inline with the existing paned-window chrome.
  - Paging the smaller `approval` view to exhaustion: after the final page loads (100 → 105 items), the button's `data-paned-activity-has-more` flips to `"0"` and it becomes `hidden`; switching to the `all` filter and back to `approval` leaves the button correctly hidden (state persisted via the DOM attribute) and the row/status count stays at 105 - no re-fetch, no duplication, confirming per-view load progress survives filter switching as required by Step 2.
  - Prev/Next stepping: selecting the last row of page 1 (id 312) then clicking "Next" once moved selection to id 232 - the exact first row of page 2 confirmed in Stage 4's pagination test - proving `stepSelection`'s fresh `rows.filter()` picks up appended rows with no gap.
  - Server log clean across all three browser-driven runs.
- Notes:
  - This completes all 5 planned stages; Step 4 (Implementation) is done pending the after-checklist and `Approved Step 4`.

## Review fixes - three defects found in manual review
The user reviewed the working Stage 5 build directly and found three problems my own Stage 5 verification missed (it checked row selection state and console errors, but never checked the detail pane's actual visible content, the left-pane counts, or the button's computed style). All three are fixed below, verified with the same real-headless-Chrome CDP approach as Stage 5, plus new checks for exactly what was missed.

- Changes:
  - **Load more button styling** (`public/assets/forte.css`): the button was picking up `public/assets/site.css`'s global `button[type="button"] { background: var(--button-alt-bg); }` (specificity `(0,1,1)`, beats a bare `.paned-list-load-more-button` class at `(0,1,0)`) plus an unopposed `button { width: 100%; }`, so it rendered as a full-width, site-themed control instead of a Forte one. Fixed by scoping the selector as `.paned-window .paned-list-load-more-button` - the exact convention `.paned-toolbar-btn` already uses in this file to win the same fight - and adding explicit `width: auto`, `margin-top: 0`, `border-radius: 0`.
  - **Left-pane per-view item counts frozen at 100** (`public/assets/paned_activity_reader.js`): `paned_activity_filter_list.php`'s `.paned-folder-count` is rendered once server-side and was never touched by the Load More flow. Fixed by updating the current view's count span after every successful load, recomputed from the live `rows` array with the same `data-paned-activity-view-<view> === "1"` test `selectFilter()` already uses (not a naive increment - see the dedup note below for why that matters).
  - **Detail pane went blank for a paginated item** (`Application.php`, new `templates/partials/paned_activity_detail_article.php`, `paned_activity_reader.js`): the detail pane's `[data-paned-activity-content-item-id]` articles were only ever rendered for the initial merged pool; a row appended via Load More had no matching article, so `selectItem()` hid every existing article (and the placeholder) with nothing left to show. Fixed the same way Stage 2 fixed row markup: extracted the per-item detail article markup (previously inline in `paned_activity_detail_pane.php`) into its own canonical partial, had `handleForteActivityPage()` render one per returned item via the same `renderFragment()` call as the row HTML, and added a new `detail_html` field to the endpoint's JSON response that the JS appends into the content pane.
  - **Duplicate rows/articles surfaced while fixing the above** (`paned_activity_reader.js`): appending `detail_html` (and, it turns out, `html`) blindly meant an item already present from a *different* view's own first-100 window (a small view's page can reach further back in time than a large view's, as the code comment on `renderForteActivity()` already explains) got a second row and a second detail article on the next Load More for another view. Two matching articles both un-hide in `selectItem()`, which is what actually produced the blank/wrong-content pane. Fixed with `mergeAppendedRows()`/`mergeAppendedDetailArticles()`: for each item in the response, if a row/article with that id already exists in the DOM, the row's `data-paned-activity-view-<view>` flag is set to `"1"` in place (so it also becomes visible under the newly-paginated view) and the article is left alone; only genuinely new ids get a new DOM node appended.
- Verification (same CDP-driven headless-Chrome approach as Stage 5, teardown after each run):
  - Button: `getComputedStyle()` on the `all` button now reports `width: 91.7px` (content-sized, not 100%) and `background: rgb(236, 233, 216)` (`--paned-chrome`, not the site's amber `--button-alt-bg`) before/after Load More.
  - Counts: `all` view folder count goes 100 → 200 after Load More, matching the status bar's own "200 items" exactly (before the dedup fix these read 138 vs 200 - a real inconsistency, not just a cosmetic gap, caught by comparing the two side by side).
  - Detail pane: selecting the newly-appended last row after Load More now shows exactly one visible `.paned-content-post` article, matching the selected row's id, with real subject/body content - not a blank pane.
  - Re-ran both prior Stage 5 CDP scripts unchanged (approval-view exhaustion + filter-switch persistence; Prev/Next stepping into appended rows) to confirm neither regressed: identical results to the original Stage 5 run.
  - Server log clean (no new PHP warnings/errors) across every run in this fix pass.
- Notes:
  - The dedup behavior also means a view's folder count and its Load More cursor can now both grow from a single click on a *different* view's button, if the two views' item pools overlap - this is expected given the existing per-view independent-fetch design (`renderForteActivity()`'s own doc comment), not a new bug.

## Follow-up - left-pane counts changed to full totals
The user asked for the left-pane folder counts to show each view's real total rather than however many happen to be loaded. Until now `count` and "how many rows are loaded" were the same number (both derived from `count($viewItemIds[$viewKey])`), so the left pane and the status bar showed the same, pagination-capped value - the original "silently caps at 100" complaint from Step 1, just relocated to the folder tree instead of the row list.

- Changes:
  - New `countActivityViewTotal(string $view): int` (`Application.php`), placed right after `fetchActivity()`. Mirrors its exact two-stage filter (the same `activityViewSql()` SQL `WHERE`, then the same tag-based PHP re-check) so the total is exactly the number of items a user could eventually page through - but selects only `board_tags_json` (skipping the ~15-column select and the expensive per-item transform `fetchActivity()` does for rendering, e.g. signature checks) and has no `LIMIT`, so it's cheap even though it's unpaginated.
  - `renderForteActivity()`'s `$viewCounts` now carries two numbers per view: `count` (the new full total, via `countActivityViewTotal()`) and `loadedCount` (the old `count($viewItemIds[$viewKey])` - how many are actually loaded into the DOM right now).
  - `paned_activity_filter_list.php` (left pane) keeps reading `$view['count']` - now the full total, unchanged markup.
  - `forte_activity.php`'s status bar switched from `count` to `loadedCount`, so it keeps showing "how many of this view are currently loaded" instead of jumping to the full total on first render.
  - `paned_activity_reader.js`: removed the folder-count-patching code added in the previous fix pass - the left-pane count is now a fixed total set once server-side and never changes client-side, so there is nothing for Load More to update there anymore (only the status bar's `loadedCount`-derived text still changes, via the existing `selectFilter()` call).
- Verification:
  - `php -l` on all changed PHP files - no syntax errors.
  - `GET /forte/activity/`: left pane now reads All Activity 1629, Visible Content 1338, Identity 269, Bootstraps 164, Approvals 105 - Approvals' 105 matches the exact end-to-end pagination count found in Stage 4's own manual test (100 + 5, page-by-page to exhaustion), confirming the fast count query agrees with the slow "actually page through everything" ground truth. Status bar still reads "100 items" (loaded, not total) on first load.
  - `time curl` on the full page: 82ms - the 5 added count queries (unindexed full-ish scans over 1629 rows, no per-item transform) added no perceptible cost.
  - CDP-driven browser check: folder count for `all` stays at 1629 before and after a Load More click; status bar moves 100 → 200 as before.
  - Re-ran every prior CDP regression script (button styling, detail-pane-for-paginated-item, approval-view exhaustion + filter-switch persistence, Prev/Next stepping) unchanged - all still pass, no console errors, server log clean.
