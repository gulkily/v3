# Forte Board Sortable Columns Step 4 Implementation Summary

## Stage 1 - Server-side sort resolution and header scaffolding
- Changes:
  - `src/ForumRewrite/Application.php`: the `^/forte/?$` route now also passes `sort`/`dir` query values into `renderForteBoard()`. Added `resolveForteBoardSort()` (validates against the four sortable columns, falls back to `''`/`''` - today's unrelated default order - for missing/unrecognized columns, and applies a per-column default direction - ascending for `subject`/`from`, descending for `date`/`replies` - when the direction is missing/invalid), `applyForteBoardSort()` (reorders `$threads` via `usort` + `array_reverse` for descending), and `forteBoardSortValue()` (the single source of truth for each column's real comparable value: lowercased `ThreadTitle::displayTitle()` for subject, lowercased author label for from, raw `root_post_created_at` for date, integer `reply_count` for replies).
  - `templates/partials/paned_board_thread_list.php`: each header cell is now `aria-sort`-bearing, wrapping a real `<button data-paned-sort-column="...">` (no click behavior yet - Stage 3).
  - `templates/pages/forte_board.php`: passes `sortColumn`/`sortDir` through to the thread-list partial.
  - `public/assets/site.css`: `.paned-sort-button` resets the global `button` styling (the same `width: 100%`-by-default issue fixed once before for the reply-toggle button) and adds a `▲`/`▼` glyph via `::after` keyed off the header span's `aria-sort` state, plus a focus-visible outline matching the rest of Forte's chrome.
- Verification:
  - `php -l` / CSS brace-balance: clean.
  - Raw HTTP checks against the live 513-thread instance: `?sort=subject&dir=asc` sorts alphabetically with `aria-sort="ascending"` on Subject only; `?sort=date&dir=asc` shows oldest-first; `?sort=date` (no `dir`) defaults to descending (newest-first) with `aria-sort="descending"`; `?sort=replies` (no `dir`) defaults to descending and shows 65, 11, 10, 9, 8, 8, 7...; `?sort=bogus` falls back to the default order with every header `aria-sort="none"`, same as no `sort` param at all.
  - Screenshot at `?sort=replies&dir=desc`: Replies header shows the ▼ glyph, rows visibly sorted highest-to-lowest.
  - Re-ran the full 39-real-tag filter check and a keyboard arrow-navigation check on the folder tree: both pass with zero regressions.
  - Full test suite re-run: 404 passing, same 4 pre-existing unrelated failures - no regressions.
- Notes: none.

## Stage 2 - Row data attributes for real sort values
- Changes:
  - `templates/partials/paned_board_thread_list.php`: each row gains `data-paned-sort-subject`/`data-paned-sort-from` (lowercased, via the same `$threadTitle()`/`$authorText()` closures already used for display - lowercased specifically to match `forteBoardSortValue()`'s case-insensitive server comparison exactly), `data-paned-sort-date` (raw ISO timestamp), and `data-paned-sort-replies` (raw integer).
- Verification:
  - `php -l`: clean.
  - Raw HTML check against the live instance: a sample row carries `data-paned-sort-subject="a tour of oodi"`, `data-paned-sort-from="ilyag"`, `data-paned-sort-date="2026-09-08T08:14:29Z"`, `data-paned-sort-replies="0"` - lowercased text and raw values as intended, not the formatted display text ("Sep 8, 2026 at ...") shown elsewhere in the row.
  - Full test suite re-run: 404 passing, same 4 pre-existing unrelated failures - no regressions.
- Notes: none.

## Stage 3 - Client-side instant re-sort on header click
- Changes:
  - `public/assets/paned_board_reader.js`: `currentSortState()` reads sort state fresh from the DOM's `aria-sort` attributes each time (single source of truth, matching how the server also expresses state via `aria-sort` - no separate JS variable that could drift from what's visually shown). `applySort(column, dir)` sorts the shared `rows` array in place using the Stage 2 data attributes (numeric compare for replies, string compare otherwise - lexicographic ISO-date comparison sorts chronologically correctly), then re-inserts each row into `listBody` via `appendChild` in the new order - moving the *existing* elements rather than rebuilding them, so each row's `hidden`/`tabindex`/`aria-selected` state survives the reorder untouched - then updates `aria-sort` on all four headers so exactly one is non-`"none"`. A click handler on the header row computes the next direction (flip if the same column is clicked again, otherwise the same per-column default used server-side) and calls `applySort()`.
- Verification:
  - `node --check`: clean.
  - Headless-browser check: selected a thread first (giving it the roving tabindex), then clicked "Subject" - list re-sorted alphabetically (quote-prefixed titles first, correct lexicographic order), `aria-sort="ascending"` on Subject and `"none"` on Date, and the previously-selected thread's row **still held `tabindex="0"`** after being moved - confirming the DOM-move approach (not a rebuild) preserved existing row state. Clicking "Subject" again correctly reversed to descending order (Cyrillic-titled threads sorting after Latin ones, as expected for code-point comparison) and flipped `aria-sort` to `"descending"`.
  - Re-ran the full 39-real-tag filter check: zero mismatches, confirming re-sorting doesn't interfere with tag-filter visibility logic.
  - Full test suite re-run: 404 passing, same 4 pre-existing unrelated failures - no regressions.
- Notes: none.

## Stage 4 - URL sync for sort state
- Changes:
  - `public/assets/paned_board_reader.js`: replaced the tag-only `urlForTag()` with `urlForState(tag, sortColumn, sortDir)`, which combines both into one URL (`?tag=&sort=&dir=`, omitting either param when empty) so tag and sort never clobber each other; `pushStateIfChanged()` centralizes the "don't push a duplicate history entry" check for both the folder-click and the sort-click handlers. The sort-header click handler now also calls `pushStateIfChanged()`. Captured `originalRowOrder` (a snapshot of the initial DOM order) right after `rows` is first read, so `popstate` can restore the true original order when navigating back past the point where any sort was ever applied - not just clear the `aria-sort` indicators. The `popstate` handler now restores both tag (as before) and sort (new): re-applies `applySort()` for a `sort` param present in the URL, or calls the new `restoreOriginalOrder()` when there isn't one.
- Verification:
  - `node --check`: clean.
  - Headless-browser check on the live instance: clicked `#bug` then "Subject" - URL became `?tag=bug&sort=subject&dir=asc`; clicked "Subject" again - `?tag=bug&sort=subject&dir=desc`. Pressed back three times in sequence and got, in order: `dir=asc` restored, then no-sort-but-`tag=bug` restored (list correctly back to `#bug`'s natural order, not sorted), then the true initial state (`/forte`, all 513 visible, "A tour of Oodi" first - confirming `restoreOriginalOrder()` correctly recovered the pre-sort order rather than leaving it in whatever order the last sort left it). Forward twice replayed both steps correctly. A direct load of `/forte?tag=testing&sort=replies&dir=asc` combined both params correctly server-side (3 visible rows, matching `#testing`'s real count).
  - Full test suite re-run: 404 passing, same 4 pre-existing unrelated failures - no regressions.
  - Re-ran the full 39-real-tag filter check and the original tag-only popstate check: both still pass with zero regressions.
- Notes: none.

## Stage 5 - Integration verification: tag filter and keyboard nav after sorting
- Changes: none - pure verification against the explicit Step 1/2 integration point; no defects found, so no code changes this stage.
- Verification (all against the live 513-thread instance):
  - Sorted by Subject (ascending), then applied the `#bug` tag filter: exactly 4 rows visible, in correct alphabetical order (`Feature requests 8/10`, `Hello world`, `Users page shows outdated data`, `When characters are updated the cursor position is reset`) - confirms sort order and filter visibility compose correctly rather than one undoing the other.
  - Arrow-keyed through that sorted+filtered list: focus moved through exactly those 4 rows in the sorted order and correctly clamped at the last one (repeated on further ArrowDown, no error, no leaking into hidden/filtered-out rows).
  - Re-sorted by Date while a row was keyboard-focused (mid-navigation): the roving tabindex correctly stayed on that exact same thread after the reorder (`matchesPreviouslyFocused: true`, still visible) - confirming the DOM-move approach from Stage 3 keeps `tabindex="0"` attached to the physical row element regardless of how sorting moves it, so a keyboard user's position is never lost mid-sort.
  - One test-script mistake surfaced along the way (not an application defect): an unscoped `document.querySelector('[tabindex="0"]')` matched the folder tree's own roving-tabindex item (earlier in DOM order) instead of the thread list's, since both panes use the same attribute independently - scoping the query to `[data-paned-board-list-body]` fixed the check. Worth remembering for any future test against this page: tabindex-based queries must be scoped per-pane.
  - Full test suite re-run: 404 passing, same 4 pre-existing unrelated failures - no regressions.
- Notes: none.
