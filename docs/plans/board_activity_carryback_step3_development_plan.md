# Step 3: Development Plan — Board/Activity Carry-Back (+ Board Score column)

## Stage 1
- Goal: Render Board and Activity list-row dates in relative format with a full-timestamp tooltip, matching Users.
- Dependencies: none — `$relativeTimestamp` closure already exists and is proven.
- Expected changes:
  - `templates/partials/paned_board_thread_list.php` (~line 77): swap the list-row date span's `$timestamp(...)` call for `$relativeTimestamp(...)`.
  - `templates/partials/paned_activity_item_row.php` (~line 25) and `paned_activity_commit_row.php` (~line 27): same swap for their list-row date spans.
  - No other `$timestamp` usage in these views changes.
- Verification approach: manual browser check of Board and Activity — list rows show "X ago" text, hovering shows the full timestamp tooltip; confirm no other date display on these pages changed.
- Risks or open questions:
  - None — mechanical swap of an already-shipped closure.
- Canonical components/API contracts touched: `$relativeTimestamp` / `$timestamp` closures in `src/ForumRewrite/View/TemplateRenderer.php` (reused as-is, no signature change).

## Stage 2
- Goal: Add a sortable Score column to Board, sourced from `threads.score_total`.
- Dependencies: independent of Stage 1.
- Expected changes:
  - `templates/partials/paned_board_thread_list.php`: add a "Score" head button (`data-paned-sort-column="score"`) alongside Subject/From/Date/Replies; add a row span rendering `(int) $thread['score_total']` with a matching `data-paned-sort-score` attribute.
  - `public/assets/paned_board_reader.js`: extend `sortValueFor()`'s numeric branch (currently special-cased for `replies`) to also parse `score` as an integer; add `score: "desc"` to the `sortDefaultDir` map.
  - Board's CSS column-width rules (`forte.css`): add a fixed-width rule for the new Score column matching the existing `replies` column pattern.
- Verification approach: manual browser check — Score column appears with correct values, clicking its header sorts ascending/descending and updates the sort URL param like other columns, no layout regression at desktop width.
- Risks or open questions:
  - `score_total` can be negative (per `TagScore`) — confirm negative values render correctly with no special formatting needed.
  - Adding a fixed-width column shrinks remaining flexible space at desktop width — confirm no regression there; the narrow-viewport squeeze bug itself is out of scope, tracked under `forte_mobile_friendly`.
- Canonical components/API contracts touched: `paned_board_reader.js` `sortValueFor()` / `sortDefaultDir` (extended, not restructured); `threads.score_total` field (read-only reuse, no schema change).
