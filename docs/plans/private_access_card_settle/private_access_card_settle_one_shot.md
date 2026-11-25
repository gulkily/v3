Cycle: `v3/private_access_card_settle`; Mode: one-shot; Record/index: [plans index](../README.md). Fifth of five one-shots from the "9 keys for ilyag want to read your messages" review (2026-11-25). Stacked on `private_access_signing_helper` (branch `fix/private_access_signing_helper`, PR #424, not merged).

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/private_access_card_settle`; host ledger [private_access_card_settle.md](../effort_ledger/private_access_card_settle.md). Standing P2 enrollment.

Extended estimates: enabled. No alternatives or work units, so no unit estimates.

**Cycle estimate: 0.15**

Status: implemented and committed; next permitted action: maintainer review (PR), after #424.

Feedback item: none matches (searched `docs/feedback/items/`).

Original request (maintainer, 2026-11-25): "'9 keys for ilyag want to read your messages' ... it performs nowhere as instant on the frontend as on the awaiting users page". Claude's review: on Users › Waiting an approved row disappears and the count drops at once; on the waiting-keys card the row stays, a small "Confirmed" appears beside it, and the heading still says "9 keys", so nothing looks finished.

Authorization: "can you do it in 5 one-shots please" (2026-11-25), on Claude's list naming "make the card behave like Waiting: remove the row, update the 'N keys' heading, and hide the card when nothing is left". Exclusions: server speed (`private_access_confirm_timing`, `private_access_one_evaluation`); the User Access list's separate row markup (kept; its rows get the same settle behaviour, the markup merge is a follow-up); merging and deploying are the maintainer's.

Bounded outcome and work type: application change (front end).
1. A successful Confirm removes that key's row at once, as Approve removes a row on Users › Waiting.
2. The card's heading follows the rows left, with the server's wording: "A new key for NAME wants to read your messages" for one, "N keys for NAME want to read your messages" for more. A "N more" drawer recounts and goes when empty.
3. A card-level status line says "Confirmed CODE." (the short code the device shows). When the last row goes, only that line stays: heading, hint and lists hide.
4. Errors stay on the row, with Retry, as today.
5. The same applies on Messages, Unread, the confirm page a QR opens, and Tools → User Access (no count there; its rows still go and the status line shows).

Approach and reuse decisions:
- Model: `pending_approvals.js`'s `bindPendingApprovals()` (remove the row, recount, show empty state, page-level feedback). That code is bound to its table markup, so it is followed, not shared; the card's settle code lives in `private_access_confirm.js`, the card's only script.
- Markup: the partial `private_access_waiting.php` gains `data-account-name` on the card, `data-role` hooks on the heading and hint, and one status `<p>`. User Access's card gains `data-private-access-waiting` and the same status `<p>`. No new CSS class; the status line uses `meta` like the row feedback.
- Wording: the heading strings in JS repeat the partial's two strings; both are commented to keep them together. No new UI text except "Confirmed CODE.", which replaces the row's "Confirmed".
- First paint is unchanged; every update follows a real state change (a Confirm).

Risks:
- **Heading wording drift between PHP and JS.** Mitigation: a browser test reads the heading after confirming one of three keys and compares it with the server's two-key wording.
- **User Access rows differ** (operator-only button, account link). Mitigation: the settle code finds rows and the card by attributes both share; the browser test covers a User Access fixture too.

Completion conditions: on Messages with several waiting keys, confirming one removes its row and lowers the heading's count; confirming the last leaves only "Confirmed CODE."; a failed Confirm keeps the row with its error and Retry. Deploy with Deploy now; no schema or flag change.

Concepts touched: [Private key access](../../concepts/private_key_access.md) (used unchanged).

Verification and recovery plan: new `tests/browser/private_access_card.mjs` (pinned `playwright-core`, Chromium; stubbed OpenPGP and fetch as in `approval_review.mjs`; desktop and phone widths); `./v3 test --smoke PrivateAccessConfirmationTest`; `node --check`; `git diff --check`. Full suite not run. Themes: the card uses existing `card`, `meta` and list styles; checked by the browser test in the default theme only, others unverified. Recovery: revert the commit.

Starting revisions and branch: `fix/private_access_card_settle` from `fix/private_access_signing_helper` `fa4219d`.

Planning commit: `39b1673` (plan and index entry only, before any implementation).

Study history:
- 2026-11-25 UTC, intake and execution entry: **0.15**. Recorded after inspecting the partial, User Access and `pending_approvals.js`, so not a clean intake. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified.

Dated execution history:
- 2026-11-25: `private_access_waiting.php`: `data-account-name` on the card, `data-role` on the heading and hint, one status `<p>`; the docblock says the script repeats the two headings. Addition.
- 2026-11-25: `user_access.php`: its waiting card gets `data-private-access-waiting` and the same status `<p>`. Addition.
- 2026-11-25: `private_access_confirm.js`: on success `settle()` removes the row, recounts the drawer (removing it when empty), rewrites the heading with the server's wording, says "Confirmed CODE.", and hides everything but the status line when no rows are left. Errors and Retry are unchanged. Change of behaviour, as authorized.
- 2026-11-25: `tests/browser/private_access_card.mjs` renders the real partial with seven keys through PHP and checks the countdown, a failed Confirm with Retry, the drawer, the one-key wording, the last-key state and a User Access card, at 1280 and 390 px. It fails (timeout) with the previous script.
- 2026-11-25: UI copy checklist entry added.

Study history, closeout:
- 2026-11-25 UTC: retrospective scope assessment **0.15**. Delivered as planned. Claude, single exposed rater. Maintainer judgment pending; measured time unknown.

Outcome: delivered. A confirmed key leaves the card as soon as the server answers, the heading counts down ("6 keys for ilyag ...", then "A new key for ilyag ..."), and the card ends as one line, "Confirmed CODE.".

Verification:
- Passed: `node tests/browser/private_access_card.mjs` (playwright-core from the environment, Chromium): all steps at both widths; fails with the old script.
- Passed: `./v3 test --smoke PrivateAccessConfirmationTest UserAccessTest`: 502 run, 502 passed. Full suite not run.
- Passed: `node --check`, `php -l` on the two templates, `git diff --check`.
- Unverified: themes other than the default (only existing `card`, `meta` and list styles are used); a real iPhone; the real page with OpenPGP.js.
- Inapplicable: icons (no navigation change), schema, flags.

Final review: the diff touches the partial, `user_access.php`, `private_access_confirm.js`, the new browser test, the UI copy checklist, this record, the plans index, the ledger and the debug log. No new CSS or component.

Follow-up noted, not done: Tools → User Access builds its waiting rows with its own `$waitingRow` instead of the partial (it adds an account link and an operator-only button). Folding it into the partial would leave one row markup.

Commit/merge/publication status: completion commit pending at the time of writing; branch `fix/private_access_card_settle`, stacked on `fix/private_access_signing_helper` (#424); PR to be opened against `main`, to merge after #424; not merged.

Debug: [debug log](./private_access_card_settle_debug_log.md).

Enhancement requests:
- One row markup for the card and User Access. Addition, proposed by Claude. Not approved. Not done.

Attribution: Claude · 2026-11-25 UTC · Model: claude-opus-5-5
