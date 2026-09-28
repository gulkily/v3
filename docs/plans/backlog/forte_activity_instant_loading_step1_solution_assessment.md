# Step 1: Solution Assessment — Forte Activity Instant Loading

## Problem statement

Forte Activity eagerly prepares every first-page record for five activity filters, including hidden detail panes and technical source metadata, so its initial work and DOM are far larger than the view the reader initially sees.

## Options

- **Option A — Reduce the initial item limit**
  - Pros: Small, low-risk change; less work and markup on every load.
  - Cons: Still prepares unused filters/details; hides more history behind pagination without addressing the eager-loading design.

- **Option B — Render a small selected-filter batch and its detail first; automatically preload all filter rows after first paint**
  - Pros: Bounds the first response to what is visible; makes every filter's complete list ready without waiting for a reader action; avoids hidden detail/metadata work and can cache rows during the visit.
  - Cons: Background work must yield to reader interaction; it still consumes post-render network/CPU time; needs careful URL, keyboard-navigation, sort, and pagination continuity.

- **Option C — Cache the fully rendered Activity page server-side**
  - Pros: Can make repeat loads fast without changing the current interaction model.
  - Cons: Cold loads and cache refreshes remain slow; invalidation is difficult after new activity; the browser still receives and parses the oversized hidden content.

## Recommendation

**Option B.** Render one small visible batch and one detail immediately, then automatically preload all list rows for every filter after first paint—without waiting for a filter click. Fetch technical detail only when its item is selected; cache each result for the visit. This preserves complete activity access without placing invisible metadata or a large hidden DOM on the critical path.

Share this document for review — **Approved Step 1** is required before Step 2.
