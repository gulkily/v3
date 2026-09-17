# Forte Activity Pagination — Step 1: Solution Assessment

## Problem
The Activity feed silently caps every view at 100 items (`ACTIVITY_ITEM_LIMIT`, inherited verbatim from classic), which looks odd and hides older activity, but loading thousands of items at once wouldn't be usable either.

## Option A: Raise the limit
Bump `ACTIVITY_ITEM_LIMIT` to a larger fixed number (e.g. 500).
- Pros: one-line change, no new UI or query logic.
- Cons: doesn't fix the underlying problem, just moves the wall further out; will look arbitrary again as the site grows; explicitly rejected by the "thousands of items wouldn't be great either" constraint.

## Option B: "Load more" pagination
Add a "Load more" control at the bottom of the item list; each click fetches the next page (offset/cursor-based) for the current filter and appends rows, reusing `fetchActivity()`'s existing per-view query with an added offset.
- Pros: small, bounded initial load; user can page as deep as they want, so "the whole picture" is reachable without a giant single payload; fits the existing list-pane/row pattern (append, don't replace); the per-row view-membership flags this feed's Stage 1 already computes extend naturally to additional pages.
- Cons: needs an offset/cursor param threaded through the 5 per-view `fetchActivity()` calls (from the prior cycle's "fetch each view separately" fix) consistently, so paging state doesn't drift between filters.

## Option C: Time-window browsing (e.g. by day/week)
Replace the flat item cap with date-bounded windows the user steps through (today/this week/older), each independently queried.
- Pros: gives real temporal orientation rather than an arbitrary count cutoff.
- Cons: meaningfully larger scope - new date-bucketing query logic per view, new UI for window selection, a bigger departure from classic's own (uncapped-by-date) behavior than this problem calls for.

## Recommendation
**Option B.** It directly answers both halves of the problem - the feed stops silently hiding data, and nothing forces a thousands-of-rows payload - with the smallest new surface area, and it composes cleanly with the per-view fetch/flag logic already in place.
