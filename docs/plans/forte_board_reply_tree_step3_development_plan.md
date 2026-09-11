# Forte Board Reply Tree — Step 3: Development Plan

## Stage 1
- Goal: Render every thread's full reply tree inline on the board page, fetched efficiently (not one query per thread).
- Dependencies: none (Step 2 approved)
- Expected changes: new private method (e.g. `fetchAllThreadReplyPosts(): array<string, array<int, array>>`) doing a single bulk query for all non-root posts across all threads, grouped by `thread_id` in PHP — avoids the N+1 pattern of calling the existing per-thread `fetchThreadPosts()` 513 times; `renderForteBoard()` builds a reply tree per thread from that grouped data (reusing `buildReplyTree()` unchanged) and passes it through; `templates/partials/paned_board_content_pane.php` renders `partials/paned_thread_reply_tree.php` inline for every thread instead of the "Show N replies" button + empty container
- Verification approach: load `/forte`, confirm every thread with replies shows its full tree immediately with no button anywhere; spot-check reply ordering/nesting matches what the old lazy endpoint used to produce for a couple of threads (including the one with 65 replies); rough timing/size check on `/forte` at current volume (513 threads / 503 replies) to confirm it's still acceptable
- Risks or open questions:
  - Confirm the bulk query's grouping preserves the same `sequence_number` ordering `fetchThreadPosts()` guarantees, since `buildReplyTree()` depends on it
- Canonical components/API contracts touched: `buildReplyTree()` (reused), `partials/paned_thread_reply_tree.php` (reused unchanged), `paned_board_content_pane.php` / `renderForteBoard()` (extended), new bulk-fetch method (new, single query)

## Stage 2
- Goal: Remove the now-dead client-side lazy-load logic and simplify new-reply highlighting to skip the expand step.
- Dependencies: Stage 1 (replies must already be inline for this to be safe/testable)
- Expected changes: `paned_board_reader.js` — remove `toggleReplies()`, `replyCache`, and the `[data-paned-reply-toggle]` click listener; simplify the existing "auto-highlight the new reply on load" logic to directly locate `[data-paned-reply-post-id="..."]` within the already-rendered tree and highlight + scroll it, with no expand call needed
- Verification approach: `node --check`; headless-browser test confirming a fresh reply-from-board round trip still lands with the new reply visible, in view, and highlighted, with no console errors; confirm no remaining references to the removed functions
- Risks or open questions: none identified
- Canonical components/API contracts touched: `public/assets/paned_board_reader.js` (reduced/simplified)

## Stage 3
- Goal: Remove the now-unused backend lazy-load endpoint.
- Dependencies: Stage 2 (nothing calls it anymore)
- Expected changes: remove the `/forte/threads/{id}/replies` route match and `renderForteThreadReplies()` from `Application.php`
- Verification approach: `php -l`; `curl` the old endpoint URL directly and confirm it now 404s; confirm `/forte` and `/threads/{id}/forte` both still render correctly
- Risks or open questions: none identified
- Canonical components/API contracts touched: `Application.php` (reduced)

## Stage 4
- Goal: Full regression and scale spot-check across the whole feature.
- Dependencies: Stages 1-3
- Expected changes: none (verification only)
- Verification approach: confirm no "Show/Hide replies" affordance remains anywhere in the board view; confirm the single-thread reader (`/threads/{id}/forte`) is unaffected (it never used the lazy endpoint); re-run the full reply-from-board-view round trip (filter + select + reply + redirect + restore + highlight, from `forte_board_reply_restore`) end-to-end against the new always-rendered tree; confirm `/forte` page weight/timing is still reasonable at current data volume
- Risks or open questions: none identified
- Canonical components/API contracts touched: none new — integration check across Stages 1-3
