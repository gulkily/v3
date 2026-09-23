# Step 3: Development Plan — Board List Column Reflow

## Stage 1
- Goal: Make Board's From/Date/Replies/Score columns size to their content (capped) instead of a fixed rem width, so Subject absorbs the freed space at half-wide window widths.
- Dependencies: none.
- Expected changes:
  - `public/assets/forte.css`: change `.paned-list-from-head`/`.paned-list-from`, `.paned-list-date-head`/`.paned-list-date`, `.paned-list-replies-head`/`.paned-list-replies`, and `.paned-list-score-head`/`.paned-list-score` from a fixed `width` to content-driven sizing (shrink-to-fit flex item) with a per-column `max-width` cap sized to each column's realistic longest value (username length for From; longest relative-date string, "59 minutes ago", for Date; digit count for Replies/Score).
  - `.paned-list-subject-head`/`.paned-list-subject` stay the single `flex: 1; min-width: 0` column — no change to Subject itself.
  - No template or JS changes — this is CSS-only.
- Verification approach: manual browser check at desktop width, half-wide window width, and the existing ~400px narrow case — confirm From/Date no longer show large empty space at half-wide, Subject visibly widens at that width, and no regression (no new squeeze, no column overlap) at the other two widths.
- Risks or open questions:
  - Confirm the chosen max-width caps don't themselves cause a squeeze at the ~400px case already tracked separately (`forte_mobile_friendly`) — this stage should not make that case worse, even though fixing it is out of scope.
  - Confirm sort-button click targets in From/Date/Replies/Score headers remain comfortably clickable once columns shrink to content width.
- Canonical components/API contracts touched: `.paned-list-head`/`.paned-list-row` flexbox rules in `public/assets/forte.css` (column-width rules only; layout mechanism itself unchanged).
