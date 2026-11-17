Cycle: `v3/key_bootstrap_test_button`; Mode: one-shot; Record/index: this file, [plans index](../README.md). Follow-up to [session_key_bootstrap](../session_key_bootstrap/session_key_bootstrap_step4_implementation_summary.md) (merged in #289).

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/key_bootstrap_test_button`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.08**

Status: planned; next permitted action: implement on `feat/key-bootstrap-test-button`.

# One-shot: a button to test lost-key recovery

## Original request

i think we need a button to test deleting the localstorage

## Understood intent

Testing session key bootstrap on a real iPhone (Stage 7 of `session_key_bootstrap`) needs the browser's stored key gone while its cookies stay. "Remove this key" logs out first, and iOS gives no easy way to clear only script storage. A button can do what Safari's seven-day rule does: clear the site's local storage, keep the cookies, then reload.

## Authorization

Ilya, 2026-11-17, in chat: the request above, after the session key bootstrap handoff listed the iPhone replay as the open item. Read as authority for a bounded one-shot that adds this one testing control. Excluded: changes to the bootstrap itself, and clearing anything the server set (cookies, session).

## Outcome and work type

Application change. With `FORUM_SESSION_KEY_BOOTSTRAP_ENABLED` on, Account → Key → Advanced → Storage and privacy shows "Test lost-key recovery". It asks first, offering "Save a copy first" as "Remove this key" does. Confirming clears local and session storage for the site and reloads. The bootstrap then gives the browser a new key. With the flag off, the button is absent.

## Approach and risks

- **The key is lost if nothing vouches.** Impact: with no backup and a failed bootstrap, the member needs a fresh approval. Mitigation: show the button only with bootstrap on; the dialog says what happens and offers "Save a copy first". Before clearing, make sure this session has recorded the browser, so the bootstrap can match. Early validation: the browser check runs the button end to end.
- **Safari also clears IndexedDB and caches.** The button clears local and session storage only, which holds the key. Recorded as a limitation; the outbox and offline cache are untouched.

## Reuse decisions

- Dialog: the `account-sheet` dialog pattern and the `data-backup-open="backup"` "Save a copy first" button from "Remove this key" (`templates/pages/account_key.php`).
- Behaviour: `public/assets/key_bootstrap.js` already loads on every page with the flag on and owns the device-recording step; the button's handler lives there.
- Flag: `FeatureFlagRegistry::SESSION_KEY_BOOTSTRAP_ENABLED`, read in `ComposeAndAccountKeyController::renderAccountKeyPage`.
- Test: extend `tests/browser/key_bootstrap.mjs` to use the button instead of clearing storage by hand.

## Completion conditions

- Normal entry: Account → Key → Advanced → Storage and privacy, with the flag on.
- End-to-end result: after confirming and the reload, the browser holds a new approved key and shows the bootstrap notice.
- Recovery: Cancel leaves everything as it was. If the bootstrap does not approve the key, the member restores the saved copy from Account → Key.
- Release: merged and deployed; then it serves the Stage 7 iPhone replay.

## Concepts touched

- [Viewer session](../../concepts/viewer_session.md): used unchanged.
- [Replaced key](../../concepts/replaced_key.md): used unchanged.

## Verification and recovery plan

- Browser check: `node tests/browser/key_bootstrap.mjs` uses the button for the wipe.
- Page render: the button appears with the flag on and not with it off.
- Smoke set plus the touched tests. Full suite not run.
- iPhone: this button is the tool for Stage 7 of `session_key_bootstrap`; that replay is recorded there.

## Starting revisions and branch

Branch `feat/key-bootstrap-test-button` from `origin/main`.

## Planning commit

Pending.

## Study history

- Intake, 2026-11-17 UTC: **0.08**, after reading the key page and bootstrap script (no pristine intake). Reference R1, protocol P2. Single exposed rater: Claude.

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*
