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
