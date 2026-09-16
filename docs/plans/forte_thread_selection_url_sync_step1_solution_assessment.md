# Forte Thread Selection URL Sync — Step 1: Solution Assessment

## Problem
Selecting a thread on Forte's board never updates the URL (only tag/sort changes do), so reloading the page loses the current thread selection.

## Option A: `pushState` on every selection, matching the tag/sort precedent exactly
Reuse `pushStateIfChanged` verbatim for all three places `selectThread()` is triggered interactively (row click, arrow-key roving-tabindex navigation, Prev/Next toolbar buttons).
- Pros: fully consistent with the existing tag/sort pattern — one rule, no special cases.
- Cons: holding an arrow key or repeatedly hitting Prev/Next floods browser back-history with one entry per thread stepped through, not per meaningful navigation — pressing Back would undo one row at a time.

## Option B: `replaceState` on every selection instead
Update the URL (enabling reload-restore) without ever adding a back-history entry.
- Pros: no history spam regardless of how selection changes; simplest single rule.
- Cons: loses any "Back steps to the previously-selected thread" behavior a reader might expect from a click (though nothing today promises that either — it doesn't exist to lose).

## Option C: `pushState` for clicks, `replaceState` for keyboard/button stepping
Treat a deliberate row click as a real navigation (back-able), but treat arrow-key/Prev-Next stepping as fine-grained adjustment of the same "view" (not back-able), mirroring how the roving-tabindex/step mechanism already treats these as a single ongoing interaction rather than discrete navigations.
- Pros: avoids history spam from rapid stepping while still giving clicks a natural Back-button undo.
- Cons: two rules instead of one — the smallest amount of inconsistency of the three options, for a real but modest UX gain.

## Recommendation
**Option B.** The user's ask is specifically "restore on reload," not "make Back step through threads" — nothing today provides the latter, so there's nothing to preserve. `replaceState` delivers the actual requirement with one simple rule and zero history-spam risk.

## Decision
**Option C**, per explicit user direction, overriding the initial recommendation (Option B) — being able to step back through previously-viewed threads is wanted, not just reload-restore. Applying `pushState` uniformly to keyboard/button stepping too (full Option A) was considered and rejected: holding an arrow key or mashing Prev/Next would flood back-history with one entry per row stepped through, making Back nearly useless during that kind of browsing. Option C gives a deliberate row click a real, back-able navigation entry (the natural case for "let me revisit a thread I was just on") while keeping fast keyboard/button stepping from spamming history — same reload-restore outcome either way, since both `pushState` and `replaceState` update the URL the restore logic reads.

**Addendum:** the click-vs-stepping split becomes a per-viewer `localStorage` preference (default matching Option C's behavior) rather than a hardcoded rule, since not every reader will want the same trade-off. No settings UI yet — just a stored default a future cycle can expose. Detailed in Step 2.
