# Step 1: Solution Assessment — Board List Column Reflow

## Problem statement
At half-wide window widths, Board's fixed-width From (9rem) and Date (13rem) columns are often wider than their content needs — especially Date, now that relative timestamps ("2 days ago") are much shorter than the old full-format text it was sized for — leaving empty space while the flexible Subject column gets squeezed.

## Related, not the same bug
`forte_mobile_friendly_step1_solution_assessment.md` (pending approval) targets a *narrower* failure: Subject collapsing to unreadable widths (~16px) at ~400px, fixed with an `overflow-x` scroll fallback. This report is a different symptom at wider ("half-wide") widths — wasted space, not unreadable squeeze — so it's written as its own assessment; Step 3 can decide whether the two share an implementation.

## Options
- **Option A — Re-tune the fixed column widths**
  - Pros: Smallest change; one CSS edit re-sizing `.paned-list-date-head`/`.paned-list-date` (and `.paned-list-from-head`/`.paned-list-from` if needed) to fit the new shorter content.
  - Cons: Still a static guess — wastes space differently at other widths, and drifts out of sync again if date/label text length changes (e.g. "11 months ago" vs "2 days ago"); doesn't help From when usernames are short.

- **Option B — Size From/Date/Replies/Score to their content (intrinsic width) instead of a fixed rem value**
  - Pros: Each column takes only what its actual content needs at any width, automatically freeing the rest to Subject; no magic numbers to re-tune when content length changes.
  - Cons: Needs a per-column cap so a single unusually long value doesn't blow out the column (e.g. `clamp(min, ch-based, max)`); still layered on the current flexbox row, so behavior is a bit more implicit than a single layout mechanism.

- **Option C — Move the row layout from flexbox to CSS Grid with content-sized tracks**
  - Pros: One layout mechanism where Subject is the single flexible track and every other column is `max-content`-sized — solves the reported wasted-space complaint directly, and is a natural base to later layer the `forte_mobile_friendly` narrow-viewport fix on top of (same track definition, just added a min-width floor + scroll fallback below it).
  - Cons: Larger touch than A/B — changes how Board's list head/rows are laid out, not just column widths, so it needs its own regression pass (Users/Activity untouched since this is Board-scoped).

## Recommendation
**Option B**, scoped to Board only. It solves the reported complaint (content-driven widths instead of static guesses) without changing the underlying layout mechanism, keeping risk and review surface small. Option C is the more thorough fix and worth revisiting if/when `forte_mobile_friendly` is approved and the two efforts get reconciled, but is more than this specific complaint needs right now. Option A is rejected as a re-run of the same static-guess problem that caused this report.

Share this document for review — **Approved Step 1** is required before Step 2.
