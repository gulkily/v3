# Forte Reactions — Step 2: Feature Description

## Problem
Forte's board has no way to Like a thread or Flag a post; classic already supports both, but the classic Like/Flag implementation assumes it's only ever showing reactions for one thread at a time.

## User Stories
- As a Forte board-view reader, I want to Like a thread and see my Like reflected immediately, so I can react the same way I would in classic.
- As a Forte board-view reader, I want to Flag a post (including a thread's root post), so problem content gets the same signal it would in classic.
- As a returning Forte board-view reader, I want threads/posts I've already reacted to to show that state (e.g. "Liked" disabled), so I don't wonder whether my earlier reaction took.

## Core Requirements
- Like (thread-level, `apply_thread_tag`) and Flag (post-level, `apply_post_tag` — including on a thread's root post) both work from Forte's board, using the existing endpoints and `thread_reactions.js` unchanged in behavior (optimistic UI, pending state, error feedback).
- `thread_reactions.js`'s thread-level binding is extended to bind every thread's Like button on the board (per approved Step 1, Option A), not just one.
- Reaction buttons trigger the same lazy identity-preparation flow already wired for signed replies (`window.__forumBrowserIdentity`, loaded via `lazy_compose_signing.js`) rather than requiring a separate load path — a reader clicking Like/Flag before ever touching the reply composer still gets prompted the same way.
- The board reflects each viewer's existing reaction state (already-Liked threads show "Liked" disabled, already-Flagged posts show "Flagged" disabled) on page load, matching classic's behavior — not a stripped-down, state-blind version.
- No changes to classic's own thread page, its reaction buttons, or the underlying tag-writing logic.

## Shared Component Inventory
- `/api/apply_thread_tag`, `/api/apply_post_tag` — canonical endpoints. **Reused unchanged.**
- `thread_reactions.js` — canonical fetch/optimistic-UI/identity logic. **Extended** (thread-level binding only, per Step 1); post-level binding already supports multiple roots and needs no change.
- Reaction button markup (`thread_root_card.php` / `post_card.php`) — **not reused directly** (Forte's reply tree renders posts via a raw-HTML builder, not shared partials); new Forte-styled buttons carry the same `data-action`/`data-tag`/`data-post-id` contract `thread_reactions.js` already expects.
- Per-viewer reaction state lookup (`viewerHasThreadTag`, `viewerPostTagsForPosts`) — the post-level helper already batches across a list of post IDs (used today for one thread's posts); **reused, extended** to batch across every thread/post on the board at once, mirroring the existing bulk-fetch pattern `forte_board_reply_tree` already established for reply trees.

## Simple User Flow
1. Reader selects a thread on the board and clicks Like (or Flag on any visible post).
2. If needed, the same identity-preparation flow already used for signed replies runs.
3. The button shows pending, then confirmed state; the thread's score or post's flag state updates in place, no page reload.
4. On a later visit, already-reacted-to items show their Liked/Flagged state immediately.

## Success Criteria
- Like and Flag both work from Forte's board and match classic's outcome exactly (same endpoints, same resulting tag records).
- Multiple threads' Like buttons on the same board page work independently and correctly.
- Existing viewer reaction state is visible on page load, not just after a fresh click.
- Classic's thread page and its reactions are completely unaffected.
