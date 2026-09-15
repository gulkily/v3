# Forte Compose Thread — Step 2: Feature Description

## Problem
Forte's board toolbar has a "New" button that's permanently `disabled` — there's no way to start a new thread from within Forte at all, only to reply to existing ones.

## User Stories
- As a Forte board-view reader, I want to click "New" and get a dialog to write a new thread, so I don't have to leave Forte to start one.
- As a Forte board-view reader who successfully creates a thread, I want to land back on the board with my new thread already selected, so I can see it in context the same way a fresh reply gets restored today.
- As a Forte board-view reader, I want to dismiss the New dialog (Cancel or Esc) without side effects if I change my mind.

## Core Requirements
- The "New" toolbar button (`forte_board.php`) becomes enabled and opens a native `<dialog>` element via `.showModal()` — a genuinely new UI pattern for this project (no `<dialog>` is used anywhere else in the codebase today), per the approved Step 1 decision (Option C).
- The dialog wraps `thread_compose_form.php` in its **full** (non-`compact`) mode — Subject, Board tags, and Body all visible — matching classic's own dedicated `/compose/thread` page, not the abbreviated inline mode `board.php`'s compact composer uses. A standalone dialog has room for the full form, and Subject shouldn't be silently dropped the way the compact mode drops it.
- `POST /compose/thread` (`handleComposeThreadSubmit()`) gains `return_to` support, the same shape as `resolveComposeReplyReturnTo()` already provides for replies: on success, the reader is redirected back to `/forte` (preserving the active tag filter) with the newly created thread selected (`selected={new_thread_id}`) — instead of today's unconditional redirect to classic's `/threads/{new_thread_id}`. This is simpler than the reply case: the server always knows the new thread's ID at redirect time, so the client only needs to supply which tag was active, not which thread to select.
- **No signed-identity support for thread creation in this cycle** — new threads created via the dialog always post anonymously (`author_identity_id` stays empty, `thread_compose_form.php`'s existing default). Reason: `lazy_compose_signing.js` binds to exactly one `[data-compose-root]` per page (`document.querySelector`, not `querySelectorAll`) — Forte's board page already spends that one slot on the Reply panel. Making the New dialog signed too would need a real fix to that shared script's single-root assumption, which is out of scope here; the original flagged authorship gap (`forte_identity_signing`) was specifically about replies, not thread creation, so this isn't a regression, just an explicit, recorded exclusion.
- Validation-error handling matches the same known, accepted limitation `forte_reply` Stage 5 already established for replies: a failed submission redirects to classic's compose-error rendering (`renderComposeThreadPage()`), not a Forte-styled inline error. Not fixed here.
- No changes to classic's own `/compose/thread` page or `board.php`'s inline compact composer — both keep using `thread_compose_form.php` exactly as they do today.

## Shared Component Inventory
- `thread_compose_form.php` — **reused unchanged**, rendered with `compact => false` inside the new dialog (the same mode `compose_thread.php` already uses).
- `handleComposeThreadSubmit()` / `POST /compose/thread` — **extended** with `return_to` handling, mirroring the existing `resolveComposeReplyReturnTo()` pattern for replies (a new sibling helper, not a modification of the reply one).
- `paned_board_reader.js` — **extended** to open/close the new dialog from the "New" toolbar button and to keep the existing `selected=`/board-restore logic working for a freshly created thread the same way it already does for an existing one.
- `forte.css` — **extended** with `<dialog>`-specific styling consistent with the paned window's existing look (matching the congruence approach already used for `.paned-compose-form`/`.paned-compose-status`).
- `lazy_compose_signing.js` / `browser_signing.js` — **not touched**; deliberately not wired to the New dialog (see Core Requirements).

## Simple User Flow
1. Reader clicks "New" on the board toolbar.
2. A dialog opens with Subject, Board tags, and Body fields (same shape as classic's `/compose/thread`).
3. Reader submits; on success, they land back on `/forte` with their tag filter preserved and the new thread selected in the list.
4. Reader can instead Cancel or press Esc to close the dialog with no thread created.

## Success Criteria
- The "New" button is no longer `disabled` and opens a working dialog.
- Submitting the dialog's form creates a thread identical in every way to one created via classic's `/compose/thread` (same write path, same validation).
- A successful submission returns the reader to `/forte` — never to classic — with the tag filter preserved and the new thread selected.
- Canceling the dialog (Cancel control or Esc) closes it with no thread created and no other side effects.
- Classic's `/compose/thread` page and `board.php`'s inline compose-thread flow are both completely unaffected.
