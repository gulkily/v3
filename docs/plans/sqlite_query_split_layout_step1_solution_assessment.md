# Step 1: Solution Assessment — SQLite Query Panel Side-by-Side Layout

## Problem
On wide screens, the SQLite viewer's query editor and results are stacked vertically (`templates/pages/sqlite_viewer.php`, `[data-role="sqlite-query-panel"]`), wasting horizontal space, and the fix should leave room for an easy future front-end toggle (user-facing control, not a server-side setting) between stacked and side-by-side, without building that control now.

## Option A: Pure CSS media-query grid switch
- Wrap editor + results in a two-column CSS grid inside the query panel card; collapse to one column below a breakpoint via `@media`
- Pros: simplest change, no new JS, mirrors the existing `min-width: 640px` breakpoint precedent in `site.css`
- Cons: a future manual toggle would need to override the media query with an extra data-attribute layer added later — some rework

## Option B: Data-attribute layout keyed like existing toggles
- Add a `data-query-layout` attribute (default `"split"`/`"stacked"` set via a small pre-paint script using `matchMedia`, same pattern as `thread_density_toggle.js`/`theme_toggle.js`); CSS rules key off the attribute instead of the raw media query
- Pros: directly reuses the codebase's two existing, working toggle patterns; adding the actual toggle UI later is a small follow-up (just flip the attribute + localStorage, infra already in place)
- Cons: slightly more setup now (small JS file) even though no toggle UI ships yet

## Recommendation
**Option B.** The feature explicitly anticipates a future user-facing toggle (deferred to a later feature, not built now), and the codebase already has two proven examples of exactly this attribute-driven pattern — building on it now avoids rework later and keeps the eventual toggle a small, low-risk addition.
