# Forte Board Sortable Columns Step 2 Feature Description

## Problem
The Forte board view's thread list only ever shows newest-first; users have no way to reorder it by subject, author, date, or reply count.

## User Stories
- As a user of the Forte board view, I want to click a column header to sort the thread list by that column, toggling ascending/descending on repeat clicks, so that I can organize threads the way I want to scan them.
- As a keyboard user, I want the column headers to be normal, Tab-reachable, Enter/Space-activatable controls so that sorting doesn't require a mouse.
- As a user who bookmarks or shares links, I want a sorted view's URL to reproduce that same sort order so that the link is trustworthy on its own.

## Core Requirements
- Each column header (Subject, From, Date, Replies) is a real button with `aria-sort` on its header cell — the same convention the existing SQLite viewer's sortable headers already use — so headers are natively keyboard-operable with no new custom navigation logic.
- Clicking a header sorts the full thread list by that column; clicking the already-active header reverses direction; clicking a different header switches to it with a sensible default direction.
- Sorting uses each row's real underlying value (a raw timestamp for Date, the numeric count for Replies), not its formatted display text - matching the SQLite viewer's existing convention of sorting real values, not rendered text.
- Sort is applied instantly client-side (no reload) and is also reflected in the URL, extending the existing `?tag=` query-string pattern with sort state, so a sorted view stays shareable and is reproduced by the server on a fresh load - consistent with how tag filtering already works in this view.
- After any re-sort, existing tag filtering and keyboard roving-tabindex navigation (both already shipped in this view) continue to operate correctly against the new row order - this is the explicit integration point flagged in Step 1, not an incidental detail.

## Shared Component Inventory
- SQLite viewer's sortable-header convention (`aria-sort` states, a sort-button class with directional glyphs) — reused as the interaction/visual pattern for a new, separate implementation; the existing code itself is tightly coupled to that tool's own data shape and isn't reusable as-is (confirmed during Step 2 research).
- Existing `?tag=` handling (`resolveForteBoardTag()`, `renderForteBoard()` in `Application.php`) — extended with `sort`/`dir` parameters following the same resolve-with-safe-fallback pattern already established.
- Existing `paned_board_thread_list.php` / `paned_board_reader.js` roving-tabindex and tag-filter logic — extended to recompute after a re-sort, not duplicated into a parallel code path.

## Simple User Flow
1. User opens the Forte board view; the thread list shows in its current default (newest-first) order.
2. User clicks the "Date" header; the list re-sorts instantly, the header shows a sort indicator, and the URL updates.
3. User clicks "Date" again; the order reverses.
4. User clicks "Subject"; the list re-sorts by subject instead, with a sensible default direction.
5. User reloads or shares the URL; the same sort order appears in the server-rendered HTML.
6. Tag filtering and arrow-key navigation continue to work correctly against the newly-sorted list.

## Success Criteria
- Clicking any of the four column headers sorts the full list by that column's real underlying value; clicking the same header again reverses direction.
- The active sort column and direction are visually and semantically indicated on the header (`aria-sort` plus a directional glyph).
- The current sort state is reflected in the URL and reproduced by the server on a fresh load of that URL.
- After sorting, tag filtering still shows exactly the correct threads, and arrow-key navigation still moves correctly through the resulting visible order.
- No new database fields, tables, or endpoints - sorting reuses the existing `/forte` route and data.
