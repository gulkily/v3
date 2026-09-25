# Step 2: Feature Description — Board/Activity Carry-Back (+ Board Score column)

## Problem
Board and Activity list rows show full-precision timestamps instead of the scannable relative format Users already has, and Board has no way to see or sort by a thread's score even though it's already computed and already drives default ordering.

## User stories
- As a Board/Activity reader, I want list rows to show "2 days ago"-style relative dates (with the exact timestamp still available on hover) so that I can scan recency at a glance, consistent with Users.
- As a Board reader, I want a visible Score column so that I can see and sort by a thread's score instead of inferring it only from default ordering.

## Core requirements
- Board's `paned_board_thread_list.php` and Activity's `paned_activity_item_row.php` / `paned_activity_commit_row.php` render dates via `$relativeTimestamp`, keeping the full-format tooltip.
- No other use of `$timestamp` in these views changes (e.g., any full-format date already shown elsewhere stays as-is).
- Board list gains a "Score" column, sortable via the existing header-button pattern, sourced from `threads.score_total`.
- New Score column follows the same head/row/sort-attribute conventions as Subject/From/Date/Replies — no bespoke sort logic.
- No database schema changes; no changes to score computation.

## Shared component inventory
- `$relativeTimestamp` / `$timestamp` closures in `src/ForumRewrite/View/TemplateRenderer.php` — reused as-is (already canonical, added in Stage 19 for Users).
- Sortable-column mechanism in `public/assets/paned_board_reader.js` (`data-paned-sort-column` / `data-paned-sort-<col>`) — reused as-is, no JS changes expected since it's column-agnostic.
- `threads.score_total` (populated by `ReadModelBuilder`) — reused as-is, no new field.

## Simple user flow
1. User opens Board or Activity.
2. List rows show relative dates instead of full timestamps; hovering a date reveals the exact time.
3. On Board, user sees a Score column and can click its header to sort ascending/descending, same as other columns.

## Success criteria
- Board and Activity list rows display relative dates matching Users' format and tooltip behavior.
- Board list displays a Score column reflecting `threads.score_total`, sortable via its header button, with sort state reflected in the URL like other Board columns.
- No regressions to existing Board/Activity sort behavior, column layout, or full-format date displays elsewhere.
