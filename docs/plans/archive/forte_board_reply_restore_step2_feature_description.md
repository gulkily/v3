# Forte Board Reply Restore — Step 2: Feature Description

## Problem
After submitting a reply from Forte's board view, the reader lands back on `/forte` with no thread selected and any active tag filter reset, losing their place entirely.

## User Stories
- As a Forte board-view reader, I want to land back on the same thread I just replied to (still selected), so that I can see my reply in context immediately instead of re-finding and re-clicking the thread.
- As a Forte board-view reader working within a tag filter, I want that filter still applied after submitting a reply, so I don't lose my place in a narrowed-down list.

## Core Requirements
- Per Step 1 (Option A), selection/filter state is carried via URL query params on the redirect target, not browser storage or server-side session state.
- The redirect after a board-view reply restores the previously selected thread as the active selection on page load (not just navigates to `/forte`).
- The redirect also restores the tag filter that was active when the reader opened the composer.
- If the previously selected thread no longer exists/is no longer visible on reload, fall back gracefully to "nothing selected" rather than erroring.
- No change to the single-thread reader's return behavior (`/threads/{id}/forte`), which already returns to the correct, specific page.
- No change to the existing tag/sort URL-state mechanism already used by prev/next and column-sort navigation.

## Shared Component Inventory
- `/forte?tag=...` query param + `resolveForteBoardTag()` (`Application.php`) — board view **already** reads a tag from the query string and pre-filters server-side. **Reused as-is** for restoring the filter; no backend change needed for this part.
- `urlForState()` / `pushStateIfChanged()` (`paned_board_reader.js`) — existing client-side URL-state pattern for tag/sort. **Extended conceptually**: the redirect's destination URL should be built the same way this code already builds `tag=`/`sort=`/`dir=` query values, rather than inventing a new format.
- Thread *selection* has no existing URL-state equivalent (only tag/sort are ever pushed to the URL) — **needs a new, small mechanism**: a `selected` query param carrying the selected thread's id, read once on page load to call the existing `selectThread()` (skipped if that id doesn't match a rendered thread).
- `partials/reply_form.php`'s `return_to` hidden field (built in `forte_reply`) and `resolveComposeReplyReturnTo()` (`Application.php`, extended in `forte_board_reply`) — **reused/extended**: `return_to` needs to be populated dynamically (current tag + selected thread) instead of the fixed `/forte` string it holds today, and the resolver's validation needs to accept that richer, still-constrained shape safely.

## Simple User Flow
1. Reader filters by a tag and selects a thread on `/forte`.
2. Reader opens the composer and submits a reply.
3. Reader is redirected back to `/forte` with both the same tag filter and the same thread selected as before.

## Success Criteria
- Submitting a reply from a filtered, thread-selected board view returns the reader to that same filter and that same thread selected — not the default "All Threads, nothing selected" state.
- Submitting with no filter/no prior selection (edge case) still works and behaves like today (lands on the plain board view).
- The single-thread reader's return-to-Forte behavior is unaffected.
