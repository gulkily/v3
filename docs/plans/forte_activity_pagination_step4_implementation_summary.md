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
