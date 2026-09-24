# Forte Activity Sortable Columns — Step 1: Solution Assessment

## Problem
The Forte Activity list's columns (Kind/Label/Date) aren't sortable like the Board view's are, but Activity's server-side cursor pagination ("Load more") means Board's pure client-side sort can't be copied as-is without misleading or incorrect results.

## Option A: Pure client-side sort (mirror Board exactly)
Reuse Board's `applySort()`/header-click pattern as-is: clicking a header re-sorts only the rows currently in the DOM; "Load more" keeps fetching in date-cursor order regardless of active sort.
- Pros: smallest possible change; reuses an existing, proven component/pattern outright.
- Cons: once a non-default sort is applied, newly loaded pages land out of order relative to it, and the visible order silently stops representing "sorted" as more loads happen - looks broken rather than incomplete.

## Option B: Server-side sort-aware cursor pagination, reset to page 1 on sort change
Extend `fetchActivity()`'s keyset cursor to be parameterized by the active sort column/direction (cursor tuple becomes `(sortValue, id)` instead of the fixed `(created_at, post_id, id)`), so every page comes back correctly ordered from the server. Activating or changing the sort discards whatever is currently loaded and re-fetches page 1 under the new sort - it never has to reconcile already-loaded rows against a new order.
- Pros: fully correct in every case, no misleading states; the reset-on-change rule means no old-rows-vs-new-sort merge logic is needed - a sort change is architecturally just "load page 1 again, with an extra parameter," reusing the same page-1-render/Load-more machinery the feature already has; sorting and unlimited "Load more" compose with no caveats.
- Cons: still needs dynamic `ORDER BY`/cursor-comparison logic per sort column on the backend, and every column offered must map to a stable, indexable-enough sort key; loses list position when you re-sort (jumps back to the top), which is expected behavior here rather than a gap.

## Option C: Client-side sort of loaded rows, "Load more" gated on default order
Same client-side `applySort()` reuse as Option A, but the "Load more" control is only enabled while the list is in its default order (unsorted/date-desc); choosing any other column hides it until sort is cleared.
- Pros: reuses Board's component like Option A; makes the loaded-vs-total distinction explicit instead of silently wrong, so there's no incorrect state to land in; no change to `fetchActivity()`/cursor logic at all.
- Cons: a user who wants both a custom sort and the full item set has to sort, note what they need, then clear sort and page further - a real but bounded UX compromise, not a correctness gap.

## Decision
**Option B**, with the reset-to-page-1-on-sort-change rule. The reset rule removes the original objection to B (open-ended cursor-reconciliation complexity), leaving a bounded, fully-correct implementation with no UX compromise for the user to work around.
