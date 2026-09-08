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
