# Forte Activity Pagination — Step 3: Development Plan

## Stage 1
- Goal: Let `fetchActivity()` page forward from a cursor instead of always returning only the first 100 rows.
- Dependencies: none (first stage).
- Expected changes:
  - `fetchActivity(string $view, ?array $afterCursor = null): array` gains an optional keyset cursor param (tuple matching the existing `created_at DESC, post_id DESC, id DESC` order).
  - Return shape becomes `{ items: array, has_more: bool }` (fetch `ACTIVITY_ITEM_LIMIT + 1` rows, trim the extra, use its presence to set `has_more`).
  - `ACTIVITY_ITEM_LIMIT` value and meaning (page size) unchanged.
  - Existing 3 other call sites (backup preview, classic `/activity/` route, RSS feed) updated to read `.items` from the new return shape; they pass no cursor, so their output is unchanged.
- Verification approach: Manually load the classic activity page, RSS feed, and an admin backup preview; confirm each renders the same first-page items as before the change.
- Risks or open questions:
  - Keyset cursor must handle a `NULL post_id` (from the `LEFT JOIN posts`) consistently with the existing ORDER BY.
  - Changing `fetchActivity()`'s return shape touches 3 unrelated call sites — must confirm none break.
- Canonical components/API contracts touched: `fetchActivity()` (`Application.php`).

## Stage 2
- Goal: Extract the per-item row markup already inline in the item-list partial into one reusable row-rendering partial, so later stages can render identical rows from two places (full page load and the new paging endpoint) without duplicating markup.
- Dependencies: Stage 1 (works against the same item shape returned by `fetchActivity()`).
- Expected changes:
  - New small partial (e.g. `templates/partials/paned_activity_item_row.php`) holding the single-row markup currently inline in `paned_activity_item_list.php`.
  - `paned_activity_item_list.php` loops and includes/calls the new row partial per item instead of inlining the markup; page output is unchanged.
- Verification approach: Manually diff the rendered Forte Activity page HTML before/after — row markup and data attributes must be byte-for-byte equivalent.
- Risks or open questions: none beyond faithfully preserving existing markup/data attributes during extraction.
- Canonical components/API contracts touched: `paned_activity_item_list.php`; new `paned_activity_item_row.php` partial (becomes canonical row renderer).

## Stage 3
- Goal: Compute per-view pagination state on the full page load and render a "Load more" control per view.
- Dependencies: Stage 1 (need `has_more` + last-item cursor per view).
- Expected changes:
  - `renderForteActivity()` captures, per view, `has_more` and a `next_cursor` (derived from the last item returned for that view) alongside the existing merged item pool.
  - `paned_activity_item_list.php` renders one "Load more" control per view (`data-paned-activity-view`, cursor value, `hidden` unless that view's `has_more` is true).
- Verification approach: Manually load the Forte Activity page against data known to exceed 100 items in at least one view; confirm the control shows only for views with more items and is hidden for exhausted views.
- Risks or open questions:
  - Cursor value needs an encoding for the HTML attribute that JS can read back verbatim in Stage 5 (plain composite string is likely sufficient; confirm during implementation).
- Canonical components/API contracts touched: `renderForteActivity()`, `paned_activity_item_list.php`.

## Stage 4
- Goal: Add a paging endpoint that returns the next page of one view, rendered with the Stage 2 canonical row partial.
- Dependencies: Stage 1 (cursor-aware `fetchActivity()`), Stage 2 (shared row partial).
- Expected changes:
  - New endpoint (e.g. `POST /api/forte_activity_page`) accepting `view` and `cursor`, reusing the same access-control check already gating the Forte Activity page.
  - Handler calls `fetchActivity($view, $cursor)`, renders each returned item through the Stage 2 row partial, and responds with `{ html: string, has_more: bool, next_cursor: string|null }`.
  - Returned items only carry the requesting view's membership flag set true; other per-view flags are not computed (paging one view does not check membership in the other 4).
- Verification approach: Manually call the endpoint (e.g. via browser devtools) for a view with >100 items; confirm returned rows continue immediately after the initial page with no overlap or gap, in the same sort order.
- Risks or open questions:
  - Must reuse the existing Forte Activity access-control gate exactly, so the endpoint isn't an unauthenticated data leak.
- Canonical components/API contracts touched: new `/api/forte_activity_page` endpoint; reuses `fetchActivity()`, `paned_activity_item_row.php`, and existing Forte Activity access-control check.

## Stage 5
- Goal: Wire client-side "Load more" behavior: fetch the next page, append it, update per-view state, and keep it consistent with filter switching and Prev/Next selection.
- Dependencies: Stage 3 (control markup + initial cursor/has_more), Stage 4 (endpoint to call).
- Expected changes:
  - `paned_activity_reader.js` gains a click handler on each view's "Load more" control: calls the Stage 4 endpoint with that view's current cursor, appends the returned HTML into the list pane, updates the control's stored cursor/has_more, and hides the control when exhausted.
  - Per-view load progress is tracked independently in existing client state so switching filters and back does not lose or duplicate loaded rows.
  - No changes to existing filter show/hide logic; `stepSelection` Prev/Next extended only enough to include newly appended rows as selectable.
- Verification approach: Manually click "Load more" on a view with >100 items in the browser; confirm rows append without duplicates, the control hides once exhausted, and switching filters then back preserves that view's loaded rows and scroll/selection behavior.
- Risks or open questions:
  - Appended rows must carry the same `data-paned-activity-id` / `data-paned-activity-view-*` attributes the existing filter/selection logic depends on, or Prev/Next and filter switching will silently miss them.
- Canonical components/API contracts touched: `paned_activity_reader.js`; consumes `/api/forte_activity_page`; depends on row markup contract from `paned_activity_item_row.php`.
