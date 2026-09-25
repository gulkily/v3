# Forte Board Reply — Step 2: Feature Description

## Problem
Forte's board view (`/forte`) has no way to reply to a thread at all — no toolbar button exists there (unlike the single-thread reader, which now has one) — forcing readers to open a thread individually just to reply.

## User Stories
- As a Forte board-view reader, I want to reply to whichever thread I have selected without leaving the board, so that I don't have to open the single-thread view just to respond.
- As a Forte board-view reader, I want the Reply action to only be available once I've actually selected a thread, so that it's clear what my reply would attach to.
- As a Forte board-view reader, I want the composer to look and feel identical to the one already in the single-thread reader, so the two views stay visually consistent.

## Core Requirements
- Add a "Reply" toolbar button to `forte_board.php` (it currently has none — not even a disabled placeholder), enabled only once a thread is selected (board view has no default selection, unlike the single-thread view).
- Clicking it reveals an in-pane composer targeting the selected thread's root post (`thread_id` + `parent_id` = that thread's `root_post_id`); no per-reply-post targeting, since board view doesn't support selecting individual replies within the expanded tree.
- The composer reuses `partials/reply_form.php` and the existing `/compose/reply` endpoint unchanged — no new backend logic.
- The composer reuses the `forte.css` classes already added for the single-thread reader (`paned-compose-panel`, `paned-compose-form`, etc.) as-is — no new styling expected.
- Selecting a different thread updates the composer's target; deselecting (no thread selected) disables Reply and hides/clears the composer.
- Submitting returns the reader to the board view (not the classic thread view), extending the existing `resolveComposeReplyReturnTo()` guard rather than duplicating it.

## Shared Component Inventory
- `partials/reply_form.php` — canonical reply form. **Reused as-is** (third surface now, after classic and single-thread Forte).
- `POST /compose/reply` / `Application.php::handleComposeReplySubmit` / `resolveComposeReplyReturnTo()` — canonical reply-submission + redirect logic. **Reused, with `resolveComposeReplyReturnTo()` extended** to also accept a board-view return path (currently only matches `/threads/{id}/forte`).
- `templates/partials/paned_compose_panel.php` (built in `forte_reply`) — **not reused directly**: it's written for a page with exactly one thread/`$thread` in scope and always-valid `root_post_id`. Board view has many threads and no default selection, so it needs a board-specific variant (or a parameterized rework) rather than a straight include.
- `forte.css`'s `.paned-compose-*` rules (built in `forte_reply`) — **reused unchanged**; no board-specific CSS anticipated.
- `paned_board_reader.js`'s `selectThread()`/`resetContentPane()` — **extended**, mirroring how `paned_reader.js`'s `selectPost()` was extended in `forte_reply` Stage 3, plus new logic for the "nothing selected" state that the single-thread view never had to handle.

## Simple User Flow
1. Reader opens `/forte`, sees the thread list with nothing selected and Reply disabled.
2. Reader clicks a thread row; it previews in the content pane and Reply becomes enabled.
3. Reader clicks Reply; an in-pane composer appears, targeting that thread's root post.
4. Reader selects a different thread while composing; the composer's target updates to match (or reader submits as-is).
5. On submit, the existing `/compose/reply` flow processes the reply and returns the reader to the board view.

## Success Criteria
- Reply is disabled with no thread selected, and becomes enabled/usable the moment a thread is selected, matching that thread's `root_post_id`.
- The composer is visually identical to the single-thread reader's (same `forte.css` classes, no new rules needed).
- Submitting creates a reply via the existing `/compose/reply` endpoint with no new backend logic beyond extending the return-path guard, and returns the reader to `/forte` afterward.
