# Forte Board Sortable Columns Step 1 Solution Assessment

## Problem Statement

As a user of the Forte board view, I want to click the thread list's column headers (Subject, From, Date, Replies) to sort the list by that column, toggling ascending/descending on repeat clicks.

## Option A: Client-side only reorder

Clicking a header reorders the already-rendered rows via JS (no new backend call, no URL change), toggling direction on repeat clicks.

Pros:
- Smallest change; instant, no reload.

Cons:
- The resulting sort order isn't shareable, bookmarkable, or verifiable via view-source — inconsistent with the tag-filter feature's established "SSR-verifiable + instant client interaction" pattern already shipped in this same view.
- Must independently derive a real sortable value per column from what's already rendered (e.g. a raw timestamp for Date, not just its formatted display text).

## Option B: Server-side sort via full page reload

Clicking a header navigates to a URL (e.g. `/forte?sort=date&dir=desc`), and the server re-renders the list in that order.

Pros:
- Simple, single source of truth for sort logic.

Cons:
- A full reload on every header click is a real step backward from the instant, in-place interaction every other Forte control (tag filter, thread selection) already has.

## Option C: Server-computed initial order + instant client-side re-sort, URL-synced

Same combined pattern already validated for tag filtering: the initial page load can be sorted via a `?sort=&dir=` query param (server pre-orders the rows, verifiable via view-source), while clicking a header re-sorts instantly client-side and updates the URL (shareable/bookmarkable), with no reload for the common case.

Pros:
- Reuses an already-built, already-verified architecture from the tag-filter feature rather than inventing a new one.
- Keeps sorted views shareable and reload-verifiable, consistent with the rest of this view.

Cons:
- Most moving parts of the three options, though all of the same kind already solved once for tag filtering.
- Client-side row reordering must keep the keyboard roving-tabindex/arrow-key navigation (which relies on DOM order matching visual order) in sync after every re-sort - a real interaction with the recently-shipped keyboard-navigation feature that needs explicit handling, not just visual reordering.

## Recommendation

Recommend Option C.

Brief justification:
- Directly reuses the SSR-plus-client-plus-URL pattern already built and validated for tag filtering in this same view, rather than a one-off approach.
- The keyboard-navigation interaction flagged above is a real integration point to plan for explicitly in Step 2/3, not a reason to avoid the otherwise-proven pattern.
