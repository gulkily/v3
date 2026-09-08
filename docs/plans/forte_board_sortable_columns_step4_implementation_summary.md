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
