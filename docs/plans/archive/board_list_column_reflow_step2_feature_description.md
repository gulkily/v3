# Step 2: Feature Description — Board List Column Reflow

## Problem
Board's From and Date list columns are fixed-width and often wider than their content needs at half-wide window widths, wasting space while the flexible Subject column gets squeezed narrower than it needs to be.

## User stories
- As a Board reader on a half-wide window, I want the From/Date/Replies/Score columns to take only the space their content needs so that Subject gets more room and is easier to scan.
- As a Board reader at any window width, I want column widths to stay stable and readable rather than needing to guess a single "right" fixed width that only works at one size.

## Core requirements
- Board's From, Date, Replies, and Score list columns size to their content (intrinsic width) instead of a fixed rem value.
- Each content-sized column has a sane cap so one unusually long value (e.g. a long username, or an older "over a year ago" relative date) can't blow the column out and eat Subject's space.
- Subject remains the single flexible column and absorbs whatever width the content-sized columns don't use.
- No change to sort behavior, column order, or which columns exist — this is a sizing change only.
- Scoped to Board only; Users and Activity list panes are untouched.

## Shared component inventory
- `.paned-list-head` / `.paned-list-row` flexbox layout in `public/assets/forte.css` — reused as the layout mechanism; only the per-column width rules change (`.paned-list-from-head`/`.paned-list-from`, `.paned-list-date-head`/`.paned-list-date`, `.paned-list-replies-head`/`.paned-list-replies`, `.paned-list-score-head`/`.paned-list-score`).
- No template or JS changes expected — sorting (`paned_board_reader.js`) and markup (`paned_board_thread_list.php`) already carry the data needed; this is a CSS-only sizing change.

## Simple user flow
1. User resizes their browser window to a half-wide width (or opens Board there directly).
2. From, Date, Replies, and Score columns shrink to fit their actual content instead of holding onto their old fixed widths.
3. Subject grows into the freed space instead of staying cramped.

## Success criteria
- At half-wide window widths, visually confirm From/Date columns no longer show large empty space next to their text.
- Subject column measurably widens (more visible characters before truncation) at the same width, compared to before.
- No layout regression at normal desktop width or at the already-fixed narrow (~400px) case — this feature doesn't need to fix that case, just not make it worse.
