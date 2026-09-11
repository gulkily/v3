# Forte Board Reply Restore — Step 1: Solution Assessment

## Problem
After replying from Forte's board view, the reader is redirected to `/forte` with no mechanism carrying their prior thread selection or tag filter forward, so both reset.

## Option A: Extend the existing URL-state pattern (query params)
- Add a new `selected` query param alongside the board's existing `tag`/`sort`/`dir` params; JS builds the composer's `return_to` dynamically (current tag + selected thread id) and reads `selected` on page load to call the existing `selectThread()`
- Pros: reuses the exact pattern already proven for tag/sort (`urlForState()`, `resolveForteBoardTag()`); state is visible/bookmarkable/refresh-safe in the URL, consistent with how this app already treats board state
- Cons: `return_to` must become dynamic (updated whenever selection/filter changes, not fixed at render time) instead of the static string used today; the redirect's target thread id needs a light existence check before calling `selectThread()` so a stale/deleted thread doesn't error

## Option B: Browser sessionStorage round-trip
- Before submitting, JS stashes the selected thread id + tag filter in `sessionStorage`; on `/forte` load, JS reads and clears it, then restores selection/filter
- Pros: no backend/redirect changes at all; `return_to` stays the static `/forte` it is today
- Cons: introduces a state-persistence mechanism not used anywhere else in Forte (all existing state is URL-driven); invisible in the URL, so a refresh or shared link after landing doesn't reflect it; another storage API to guard against (private browsing, disabled storage)

## Option C: Server-side sticky selection (session/cookie-backed)
- Server remembers the reader's last-selected thread and pre-renders it as selected
- Pros: works without relying on client JS timing
- Cons: this app has no per-user session/cookie infrastructure today; introduces new state-management surface for a purely cosmetic UI concern; clearly disproportionate versus A/B

## Recommendation
**Option A.** It extends a pattern this codebase already committed to for board state (tag/sort in the URL) rather than introducing a new one, keeps the fix entirely client-visible and debuggable, and needs no new persistence layer. Option C is out of proportion to the problem; Option B works but would be the only piece of Forte state that isn't URL-addressable, which cuts against consistency for no real benefit here.
