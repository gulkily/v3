# Forte Compose Thread — Step 3: Development Plan

## Stage 1
- Goal: Get a working "New Thread" dialog on the board — reader can open it, fill it in, and successfully create a thread.
- Dependencies: none (Step 2 approved)
- Expected changes: new partial (e.g. `paned_board_new_thread_dialog.php`) wrapping a `<dialog>` around `thread_compose_form.php` in full (`compact => false`) mode, included from `forte_board.php`; the "New" toolbar button loses its `disabled` attribute and gets a `data-paned-board-new` hook; `paned_board_reader.js` gains a click handler that calls `.showModal()` on the dialog.
- Verification approach: `php -l`; headless-browser test — click "New", confirm the dialog opens with Subject/Board tags/Body fields visible, fill in a real thread, submit, confirm the thread is actually created (write path unchanged from classic's own `/compose/thread`, so this reuses the existing writer). Redirect target after success is still classic's `/threads/{new_thread_id}` at this stage — expected and fixed in Stage 2, not a regression to chase down now.
- Risks or open questions:
  - `thread_compose_form.php` ships a "Clear fields" button and a second "Create thread anonymously" submit button, both of which are only wired up by `browser_signing.js` (`data-action="clear-compose-fields"` / `submit-anonymous-compose"` handlers live entirely inside that shared script). Per Step 2's decision not to load signing for this dialog (no `data-compose-root`), both will render but be functionally inert-or-redundant: "Clear fields" does nothing, and the second submit button behaves identically to the first (both just post anonymously, since nothing else differentiates them without JS). This mirrors already-accepted dead-UI debt elsewhere in Forte (e.g. New/Refresh toolbar buttons) rather than introducing a new kind of problem — accepted, not fixed, but confirmed and recorded here rather than found later by surprise.
- Canonical components/API contracts touched: `thread_compose_form.php` (reused unchanged), `forte_board.php` / `paned_board_reader.js` (extended), new dialog partial (new, thin wrapper only).

## Stage 2
- Goal: Make a successful thread creation land back on Forte's board instead of bouncing to classic.
- Dependencies: Stage 1 (need a working submission path to redirect from)
- Expected changes: `handleComposeThreadSubmit()` gains `return_to` handling via a new `resolveComposeThreadReturnTo()` helper (a sibling to `resolveComposeReplyReturnTo()`, not a modification of it — thread creation doesn't have an existing thread ID to whitelist against); on success, redirects to `/forte` with the active tag preserved and `selected={new_thread_id}` appended, using the ID the server already generated (`$result['thread_id']`), so the client only ever needs to supply `tag`. The dialog's form gets a hidden `return_to` field populated by a small extension to `paned_board_reader.js`'s existing `composeReturnToUrl()`-style logic (reused, called without a thread ID since none exists yet — produces `/forte?tag=...` with no `selected`).
- Verification approach: `php -l`; live end-to-end test via the dialog — submit a new thread with a tag filter active, confirm the redirect lands on `/forte?tag=...&selected={new_thread_id}`, and confirm the board's *existing, unmodified* `selected=` restore logic in `paned_board_reader.js` already picks it out correctly (no changes needed there — it already works off whatever thread ID is present in the freshly-rendered row list, new or old).
- Risks or open questions: none identified — this reuses the same `return_to` mechanism already proven twice (`forte_board_reply_restore`, `forte_identity_signing`).
- Canonical components/API contracts touched: `Application.php` (`handleComposeThreadSubmit()` extended, `resolveComposeThreadReturnTo()` new), `paned_board_reader.js` (extended).

## Stage 3
- Goal: Interaction and visual polish — dismissal, focus, and styling consistent with the rest of the paned chrome.
- Dependencies: Stage 1 (dialog must exist)
- Expected changes: an explicit Cancel control inside the dialog (native `<dialog>` Esc-to-dismiss is free, but an on-screen close affordance is added for parity with the rest of Forte's UI, which has no keyboard-only affordances documented as a hard requirement); Subject field auto-focused on open; `forte.css` gains `<dialog>`-specific rules matching the paned window's existing look (borders/chrome/typography), consistent with how `.paned-compose-form`/`.paned-compose-status` were styled for the Reply panel.
- Verification approach: headless-browser test — Cancel and Esc both close the dialog with no thread created and no other side effects; Subject field has focus immediately after `.showModal()`; screenshot check that the dialog's styling reads as part of the paned window, not a generic browser dialog.
- Risks or open questions:
  - Repeated open/cancel/reopen cycles: since the `<dialog>` stays in the DOM across opens (this isn't a fresh page load), confirm whether previously-typed field values should persist or reset on reopen. Default to whatever plain HTML gives for free (values persist since the DOM node persists) unless that reads as confusing in the live test — no explicit reset-on-open logic planned unless the test surfaces a real problem.
- Canonical components/API contracts touched: `forte.css` (extended), `paned_board_reader.js` (extended, dialog-open focus/Cancel handling).

## Stage 4
- Goal: Full regression check across the whole feature.
- Dependencies: Stages 1-3
- Expected changes: none (verification only)
- Verification approach: confirm classic's `/compose/thread` page and `board.php`'s own inline compact composer are both byte-for-byte unaffected; confirm Forte's existing Reply flow (anonymous and signed) still works exactly as before (the New dialog shares no markup or JS state with the Reply panel beyond the toolbar/board scaffolding); confirm the board's tag filter and sort state survive a full create-thread round trip; confirm no console errors from the intentionally-inert Clear-fields/anonymous-submit buttons noted in Stage 1.
- Risks or open questions: none identified.
- Canonical components/API contracts touched: none new — integration check across Stages 1-3.
