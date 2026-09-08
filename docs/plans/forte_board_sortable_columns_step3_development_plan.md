# Forte Board Sortable Columns Step 3 Development Plan

## Stage 1 - Server-side sort resolution and header scaffolding
- Goal: resolve optional `sort`/`dir` query params against a fixed set of valid columns, sort `$threads` server-side accordingly, and render each column header as a real button with the correct initial `aria-sort` state - no click behavior yet.
- Dependencies: none.
- Expected changes: the `^/forte/?$` route dispatch also extracts `sort`/`dir`; a new pure resolver analogous to `resolveForteBoardTag()` (e.g. `resolveForteBoardSort(string $column, string $dir): array{column: string, dir: string}`) that falls back to today's default (newest-first) when absent/invalid; a sort-application step over `$threads` using each column's real value (subject via the existing title-fallback logic, author label, `root_post_created_at`, `reply_count`); `paned_board_thread_list.php`'s header cells become buttons with `aria-sort` reflecting the resolved state.
- Verification: manual/automated check - `GET /forte?sort=subject&dir=asc` server-renders threads in alphabetical-by-subject order with the Subject header's `aria-sort="ascending"`; an invalid/missing sort falls back to today's default order with every header `aria-sort="none"`.
- Risks/open questions: fix the exact per-column comparison rule (e.g. case-insensitive subject) once here, since the client-side implementation in Stage 3 must match it exactly or the two could disagree.
- Touches: `Application.php`, `paned_board_thread_list.php`.

## Stage 2 - Row data attributes for real sort values
- Goal: expose each row's real, comparable sort value as data attributes so client-side sorting doesn't need to scrape formatted display text.
- Dependencies: Stage 1 (establishes which columns/values matter).
- Expected changes: each `.paned-list-row` gains `data-paned-sort-subject`, `data-paned-sort-from`, `data-paned-sort-date` (raw ISO timestamp), and `data-paned-sort-replies` (raw integer) attributes alongside its existing ones.
- Verification: manual check - raw HTML response includes correct, comparable values in these attributes (e.g. the raw ISO date, not the "Sep 8, 2026 at ..." display text) for a few sample threads.
- Risks/open questions: none expected; a straight additive attribute pass.
- Touches: `paned_board_thread_list.php`.

## Stage 3 - Client-side instant re-sort on header click
- Goal: clicking a header button reorders the visible DOM rows instantly (toggling ascending/descending), using the Stage 2 data attributes, and updates all headers' `aria-sort` state.
- Dependencies: Stages 1-2.
- Expected changes: a click handler on the header row that reads the clicked column's sort key from each row's data attribute, sorts a working copy of the row list, and moves the existing row elements into the new order (not rebuilt from scratch, so each row's current `hidden`/`tabindex`/`aria-selected` state survives the move); updates `aria-sort` across all four headers (only the active one non-"none"); updates the shared row-order reference so tag filtering and roving-tabindex/arrow-key navigation keep working against the current order.
- Verification: manual/automated check - clicking "Subject" reorders visible rows alphabetically and clicking it again reverses order; "Date"/"Replies" sort chronologically/numerically using the raw data attributes, not the displayed text.
- Risks/open questions: moving DOM nodes (not rebuilding them) is the specific mechanism required to preserve existing row state through a re-sort - flagged explicitly so it isn't accidentally implemented as a re-render.
- Touches: `public/assets/paned_board_reader.js`.

## Stage 4 - URL sync for sort state
- Goal: clicking a header updates the URL (`?sort=&dir=`, combined with any existing `?tag=`) via `history.pushState`, and back/forward restores prior sort state - mirroring the existing tag-filter URL sync.
- Dependencies: Stage 3.
- Expected changes: extend the existing URL-building helpers (or add analogous ones) to also encode sort/dir alongside tag; extend the existing `popstate` handler to also re-apply sort state from the URL, not just tag.
- Verification: manual/automated check - clicking a header updates the URL; back/forward restores the correct combination of tag filter and sort state together; re-clicking the same header/direction doesn't push a duplicate history entry, matching existing tag-filter behavior.
- Risks/open questions: tag and sort state must combine correctly in one URL (e.g. `/forte?tag=bug&sort=date&dir=asc`) without one clobbering the other.
- Touches: `public/assets/paned_board_reader.js`.

## Stage 5 - Integration verification: tag filter and keyboard nav after sorting
- Goal: confirm the explicit Step 1/2 integration point - after any re-sort, tag filtering still shows exactly the right threads, and arrow-key roving-tabindex navigation still moves correctly through the new visible order (including the tabindex-recovery edge cases already built for filtering/collapsing).
- Dependencies: Stages 1-4.
- Expected changes: none anticipated beyond small fixes if a defect is found, matching the successful pattern from the keyboard-navigation feature's own final stage.
- Verification: manual/automated check - sort by a column, then apply a tag filter, and confirm correct visible rows; with a tag filter active, arrow-key through the sorted+filtered list and confirm order and skip-hidden behavior are both correct; re-sort while a row is keyboard-focused and confirm the roving tabindex follows to the right row rather than getting stranded.
- Risks/open questions: none expected; verification-only unless a concrete defect surfaces, in which case the fix stays within files already touched in Stages 1-4.
- Touches: none expected.
