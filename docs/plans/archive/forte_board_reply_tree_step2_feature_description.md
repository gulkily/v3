# Forte Board Reply Tree — Step 2: Feature Description

## Problem
The board view hides each thread's replies behind a "Show N replies" button that fetches them over the network on click, adding an unnecessary extra step and a noticeable delay.

## User Stories
- As a Forte board-view reader, I want a thread's full reply tree already visible when I select it, so that I don't have to click an extra button and wait for it to load.
- As a Forte board-view reader who just posted a reply, I want to land on my new reply already visible in the tree, so I don't need an extra expand step to see it.

## Core Requirements
- Selecting a thread on the board immediately shows its complete reply tree inline below the thread body — no button, no fetch, no collapsed state.
- The board page renders each thread's full reply tree server-side up front, the same way the single-thread reader already does, rather than a reply-count summary plus an on-demand fetch.
- The "Show/Hide replies" button, its lazy-load endpoint, and the client-side fetch/cache logic behind it are removed as dead code once every thread's tree is embedded up front.
- Board page load stays acceptable at current data volume (513 threads, 503 replies total, one thread as high as 65) — this is a known trade-off accepted in Step 1, not something to re-litigate here, but a regression check belongs in success criteria.
- The existing "new reply gets scrolled into view and lightly highlighted" behavior (`forte_board_reply`) keeps working now that the tree is always present, without needing an expand step first.

## Shared Component Inventory
- `templates/partials/paned_thread_reply_tree.php` — the existing reply-tree renderer, already used by both the single-thread reader and the (soon-removed) lazy board endpoint. **Reused as-is**, just invoked eagerly per thread during board render instead of once per click.
- `buildReplyTree()` (`Application.php`) — canonical tree-building helper, already used elsewhere in Forte. **Reused**, called once per thread at board-render time instead of once per click.
- `/forte/threads/{id}/replies` endpoint + `renderForteThreadReplies()` — **removed**; no longer needed once every thread's tree ships with the initial board render.
- `paned_board_reader.js`'s `toggleReplies()` / `replyCache` / reply-toggle click handling — **removed** as dead code; the existing highlight-and-scroll-to-new-reply logic is simplified to skip the now-nonexistent "expand first" step.

## Simple User Flow
1. Reader opens `/forte`.
2. Reader selects a thread.
3. Reader immediately sees the thread body and its complete reply tree together, with no extra click or visible delay.

## Success Criteria
- No "Show N replies" button or collapsed-reply state remains anywhere in the board view.
- Selecting any thread shows its full reply tree immediately.
- Board page load time/size at current data volume (513 threads / 503 replies) stays acceptable — spot-checked, not required to match the old lazy-load page weight exactly.
- The new-reply highlight-and-scroll behavior from `forte_board_reply` still works correctly against the always-rendered tree.
