# Forte Board Reply Tree — Step 1: Solution Assessment

## Problem
The board view's "Show N replies" button feels unnecessary and has a noticeably delayed response, since it lazily fetches each thread's reply tree over the network only after the reader clicks it.

## Option A: Eagerly render every thread's replies on initial page load
- Remove the toggle button and `/forte/threads/{id}/replies` endpoint entirely; server-render every thread's full reply tree inline in the initial `/forte` HTML, always visible
- Pros: fully eliminates the click-and-wait; simplest resulting code (no fetch, no cache, no expand/collapse state)
- Cons: this board currently has 513 threads totaling 503 replies (one thread alone has 65) — embedding all of that in every `/forte` page load reintroduces the exact page-bloat concern the lazy-load endpoint was originally built to avoid (per its own code comment), even though most visits only ever open one or two threads

## Option B: Auto-load replies immediately on thread selection (no button)
- Remove the toggle button/collapsed state, but keep the existing per-thread `/forte/threads/{id}/replies` fetch — trigger it automatically the moment a thread is selected, so replies just appear under the thread body without an extra click
- Pros: removes the "unnecessary button" and the extra interaction step entirely; keeps the board's initial page payload exactly as small as today (only the selected thread's replies are ever fetched); no backend change needed, only client wiring
- Cons: doesn't eliminate network latency itself — there's still a brief fetch after selecting a thread, just without a separate click to trigger it

## Option C: Hybrid — eager for the initially-selected thread, auto-load for the rest
- Server pre-renders replies inline only for whichever thread is selected via the existing `?selected=` URL state (e.g., right after replying); any other thread picked afterward uses Option B's auto-fetch-on-select
- Pros: zero latency for the one case that matters most today (returning to a thread right after replying); otherwise same footprint as Option B
- Cons: two different code paths (server-rendered vs. client-fetched) for what should be the same UI; added complexity for a narrow benefit

## Decision
**Option A**, per explicit user direction, overriding the initial recommendation (Option B). This fully eliminates the click-and-wait rather than just removing the extra click, accepting the added page weight as the trade-off — at this board's current scale (503 replies total across 513 threads), that's a modest amount of extra HTML, not the unbounded growth the original lazy-load comment was guarding against in the abstract.
