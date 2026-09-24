# Forte Activity Sortable Columns — Step 2: Feature Description

## Problem
The Forte Activity list's columns (Kind/Label/Date) can't be sorted the way Board's can, and Board's pure client-side sort can't be reused as-is because Activity paginates via a server-side cursor that Board doesn't have.

## User Stories
- As a user reviewing Activity, I want to click a column header to sort by it, and click again to reverse direction, the same way I already can on Board.
- As a user who has sorted the list, I want "Load more" to keep fetching in that same order so the list never goes out of order as I load further pages.
- As a user, I want a clear visual indicator of which column and direction is currently active.

## Core Requirements
- Each sortable column (Kind, Label, Date) toggles ascending/descending on click, mirroring Board's header-button/`aria-sort` interaction exactly.
- Sorting is enforced server-side: `fetchActivity()`'s ordering and keyset cursor become parameterized by the active sort column/direction instead of being hardcoded to `created_at DESC`.
- Activating or changing the sort (including clearing it) discards whatever is currently loaded for the current view and reloads page 1 fresh under the new order.
- "Load more" continues paging in the currently active sort order with the same no-gap/no-duplicate guarantee today's date-order pagination already has.
- The default (unsorted) state is unchanged from today's behavior (`created_at DESC`).

## Shared Component Inventory
- Board's sortable header markup (`paned_board_thread_list.php`'s `data-paned-sort-head`/`paned-sort-button`/`aria-sort`, styled by `forte.css`'s `.paned-sort-button` rules) - Activity reuses this exact markup/CSS pattern for its own column headers rather than inventing new sort-UI.
- Board's click-to-toggle-direction interaction shape in `paned_board_reader.js` (`applySort`) - Activity's reader JS follows the same interaction shape, but can't reuse it verbatim: Activity's sort must trigger a server re-fetch (reset to page 1) rather than a pure client-side re-sort, since Activity paginates and Board doesn't.
- `fetchActivity()` (`Application.php`) - extended, not forked, to accept a sort column/direction alongside its existing view + cursor parameters.
- `/api/forte_activity_page` endpoint - extended to accept the same sort parameters, so "Load more" requests continue in the active sort order.
- `paned_activity_item_list.php` (row-list partial) - extended with sortable column headers; the row-rendering partial itself (`paned_activity_item_row.php`) is unaffected.

## Simple User Flow
1. User opens Forte Activity; the list defaults to today's date-descending order.
2. User clicks a column header (e.g. "Kind"); the list reloads from the top, sorted by that column ascending, with the header showing the active direction.
3. User clicks the same header again; the list reloads from the top, now descending.
4. User clicks "Load more"; the next page loads, continuing in the currently active sort order.
5. User switches filter view or clears the sort; the list reloads from the top under the resulting order.

## Success Criteria
- Every sortable column supports both ascending and descending, matching Board's interaction pattern.
- The visible list is always fully and correctly ordered by the active sort, no matter how many pages have been loaded.
- "Load more" never produces a gap, duplicate, or out-of-order item relative to the active sort.
- Changing the sort always starts from a clean page 1, with no stale rows left over from the prior sort.
