# Forte Board Reply — Step 3: Development Plan

## Stage 1
- Goal: Add a hidden, board-appropriate compose panel and a disabled toolbar Reply button to the board view (markup only).
- Dependencies: none (Step 2 approved); builds on `forte_reply`'s already-shipped `reply_form.php`/`forte.css` reuse
- Expected changes: new `templates/partials/paned_board_compose_panel.php` (generic, not tied to a single `$thread` — initial `thread_id`/`parent_id` render empty since no thread is selected by default), included once from `templates/partials/paned_board_content_pane.php` after the per-thread loop; `templates/pages/forte_board.php` toolbar gets a new `disabled` "Reply" button with a `data-paned-board-reply` hook (mirrors the placeholder `forte.php` originally had)
- Verification approach: load `/forte`, inspect DOM for the hidden panel with empty `thread_id`/`parent_id` and the disabled toolbar button; confirm single-thread `/threads/{id}/forte` view is unaffected
- Risks or open questions: none identified
- Canonical components/API contracts touched: `partials/reply_form.php` (reused unchanged), `paned_board_content_pane.php` / `forte_board.php` (extended)

## Stage 2
- Goal: Wire the Reply button to toggle the panel, and make it enabled/disabled based on whether a thread is currently selected.
- Dependencies: Stage 1
- Expected changes: `public/assets/paned_board_reader.js` gets a click handler on `[data-paned-board-reply]` toggling the panel's `hidden` attribute; `selectThread()` enables the button, `resetContentPane()` (the existing no-selection path) disables it and hides the panel
- Verification approach: headless-browser test on `/forte` — confirm Reply starts disabled with nothing selected, becomes enabled after selecting a thread, and toggles the panel open/closed; visually compare the opened panel against the single-thread reader's composer to confirm the reused `forte.css` classes render identically with no new rules needed
- Risks or open questions: none identified
- Canonical components/API contracts touched: `public/assets/paned_board_reader.js` (extended)

## Stage 3
- Goal: Keep the compose panel's target thread in sync with board selection, including the no-selection case.
- Dependencies: Stage 2
- Expected changes: `selectThread()` updates the panel's hidden `thread_id`/`parent_id` inputs to the selected thread's `root_post_id`; `resetContentPane()` clears both fields back to empty (paired with disabling Reply/hiding the panel from Stage 2)
- Verification approach: headless-browser test — select thread A, confirm fields match; select thread B, confirm fields update; trigger a folder/tag filter that hides the selected thread (existing `resetContentPane` path) and confirm fields clear and Reply disables
- Risks or open questions: none identified
- Canonical components/API contracts touched: `public/assets/paned_board_reader.js` (extended)

## Stage 4
- Goal: Return to the board view after replying from it, reusing (not duplicating) the redirect guard built in `forte_reply`.
- Dependencies: Stage 3
- Expected changes: extend `Application.php::resolveComposeReplyReturnTo(string $requestedReturnTo, string $threadId): string` to also accept the literal `/forte` board-view path (in addition to the existing `/threads/{threadId}/forte` pattern) as a valid return target; `paned_board_compose_panel.php` passes `returnTo = '/forte'`
- Verification approach: `curl -i -X POST /compose/reply` with `return_to=/forte` → redirect Location targets `/forte?...`; same request with no `return_to` → unchanged classic fallback; same request with a malicious/unrecognized `return_to` → falls back to classic location (regression check on the existing guard); confirm the new reply appears on `/forte` after submitting from there
- Risks or open questions:
  - Board view's tag filter/sort state isn't part of the redirect target, so returning to `/forte` always lands on the unfiltered view — acceptable per Step 2 (not a stated requirement), but worth confirming with the user isn't surprising
- Canonical components/API contracts touched: `Application.php::resolveComposeReplyReturnTo` (extended), `partials/reply_form.php` (`return_to` field reused unchanged)
