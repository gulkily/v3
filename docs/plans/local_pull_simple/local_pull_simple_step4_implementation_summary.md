> **Feature plan:** [Step 1](./local_pull_simple_step1_solution_assessment.md) · [Step 2](./local_pull_simple_step2_feature_description.md) · [Step 3](./local_pull_simple_step3_development_plan.md) · [Step 4](./local_pull_simple_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_pull_simple`; [cycle ledger](../effort_ledger/local_pull_simple.md). Standing P2 enrollment; parent `v3/private_message_local_pull`.

Extended estimates: enabled

**Cycle estimate: 0.85**

Branch `feature/local_pull_simple` from `origin/main` `fd500820`. Planning commit `9457c988`.

## Step 4 Entry

2026-11-27T21:00Z: Step 3 projection reaffirmed, **0.85 projected total**. Stage estimates copied from Step 3: 0.20, 0.15, 0.25, 0.15, 0.15, 0.10. Claude, single exposed rater.

## Stage 1 - Key handoff on zenmemes.com

**Original estimate: 0.20** (unchanged)

- Changes:
  - New route `/account/key/share?to=<origin>` in `Application`: `GET`/`HEAD`, allowed in members-only mode like `/account/key/`, and listed among the application routes. It is served by `ComposeAndAccountKeyController::accountKeyShare()`.
  - The server accepts `to` only if it matches `^http://(127\.0\.0\.1|localhost|\[::1\]):[0-9]{1,5}$`, and otherwise renders "This link has no valid local address."
  - New `templates/pages/account_key_share.php` and `public/assets/key_handoff.js`. The script checks the target pattern again and requires `window.opener`. It shows the key's username and short fingerprint, and **Use it there** posts `{type: 'forum-key-handoff', version: 1, privateKey, fingerprint, username}` to the opener with `targetOrigin` set to the target, then closes. The refusals (bad target, no opener) are a short message plus closed Details. A browser without a key gets a link to Account.
- Verification:
  - New `node tests/browser/key_handoff.mjs`: passed. It uses a real instance and two plain loopback "local copy" pages, and checks:
    - nothing arrives before the click;
    - after the click, exactly one message arrives from the source origin with the right key, and the popup closes;
    - `?to=` naming the other local page delivers nothing to the opener;
    - `https://127.0.0.1`, `http://evil.example`, `http://127.0.0.1.evil.example` and `javascript:` targets show no button;
    - opened without an opener: refused with Details naming `key handoff: opener`;
    - no key in the browser: an Account link, nothing sent.
  - Smoke set: 497 run, 497 passed. The full suite was not run.
  - Screenshot at the popup size checked, and the heading copy trimmed.
- Notes:
  - Retrospective stage-work assessment: **0.20**.

## Stage 2 - Local page receives the key

**Original estimate: 0.15** (unchanged)

- Changes:
  - `/local/` gets **Connect to <host>**. The click opens the source's `/account/key/share?to=<this origin>` in a popup with a new name each time. If the popup is blocked, it shows a fallback link (`rel="opener"`) instead.
  - `local_message_pull.js` accepts a message only if `event.origin` is the source origin, `event.source` is the popup it opened (when known), the type is `forum-key-handoff` and the version is 1. It waits 120 s, then reports a timeout with Details (`step: local pull: connect handoff`).
  - The key is installed through `window.__forumBrowserIdentity.importPrivateKey()`, which is the backup-restore function `importAccountPrivateKey`. If this browser already has a different key, a Replace / "Keep this browser's key" box asks first; Replace passes `{replace: true}`, which skips only the previous-fingerprint check.
  - `/local/` now loads `openpgp_loader.js`, `browser_signing.js` and `private_message_history_crypto.js`.
  - Merged the local branch of #454 (`fix/local-pull-account-diagnostics`) for `LocalPullFailure`, the Details renderer and the 180 s relay timeout. **This PR depends on #454.**
  - **Fixed a pre-existing bug** found while checking the shared restore function: on Account, "Use a key from another device" and "Save a copy of this key" did nothing. Deferred scripts run while `readyState` is already `"interactive"`, so `browser_signing.js` bound the page before the later-deferred `key_backup_ui.js` defined `ForumKeyBackupUI`, and the dialog was never wired. It now binds when the backup UI is present, or on `DOMContentLoaded`/`load`.
  - **Fixed a stale test:** `key_backup_ui.mjs` waited 15 s for the aborted-identity-hint restore to finish, but since 836ca592 publishing retries for about 15 s. Those two waits now allow 60 s.
- Verification:
  - `key_handoff.mjs` adds three cases, all passing: Connect installs the key; a different key prompts first, and Keep leaves it alone while Replace installs; a wrong-origin message is ignored and the timeout shows Details.
  - `key_backup_ui.mjs` failed on the base commit, and now passes in both text and PNG formats (Chromium to Firefox).
  - `LocalDevelopmentImportTest` in the smoke set: 502 run, 502 passed.
- Notes:
  - The Firefox browser for Playwright had to be installed in the workspace (`npx playwright install firefox`).
  - Retrospective stage-work assessment: **0.20** (the pre-existing backup-dialog bug added time).

## Stage 3 - Connect runs the rest

**Original estimate: 0.25** (unchanged)

- Changes:
  - After the key is installed, Connect runs each remaining step in turn with its own status line:
    1. `key_status`, which requires the key to be registered and approved on the source and the usernames to agree;
    2. an import through `/local/`'s JSON endpoint (`status`, then `import` or `resume`, then polled), **only if this clone does not know the key yet**;
    3. `pull`;
    4. `trust_candidates`, then one `trust` per key, this browser's key first, with no list shown;
    5. `saved_copies`.
  - If senders' keys are missing and no import ran in this pass, it imports once more and asks again.
  - The stage (`start`, `import`, `pull`, `trust`, `saved`) is kept in `sessionStorage` (`forum-local-connect`, same source, 30-minute limit). On load, a saved stage resumes without the popup, waiting for a running import first, so `import_control.js`'s reload falls in the middle harmlessly. A failure keeps the stage, so pressing Connect again continues.
  - New relay action `saved_copies`: it turns on `FORUM_PRIVATE_MESSAGE_CACHE_ENABLED` with `LocalWriteService::setFeatureFlag()`. If the value comes from the environment or private config, it reports `overridden_by` and changes nothing. Connect then says Messages will decrypt on every visit.
- Verification:
  - New `LocalDevelopmentImportTest::testSavedCopiesTurnsOnTheCacheFlagOnceAndReportsAnOverride`: passed. It checks the first call writes the record, the second writes nothing, and `FORUM_PRIVATE_MESSAGE_CACHE_ENABLED=false` in the environment is reported as `overridden_by: environment`.
  - `key_handoff.mjs` still passes.
  - Smoke set plus `LocalDevelopmentImportTest`: 503 run, 503 passed.
  - The two-instance browser run comes in Stage 5, as planned.
- Notes:
  - **Deviation:** the pull runs before trust, not after. `trust_candidates` lists the keys that signed pulled mail, so it is only complete after the pull. The pull itself needs only that this clone knows the key.
  - **Deviation:** the import runs only when needed, instead of on every Connect. A full import of zenmemes.com takes minutes, and a known key with no missing senders needs none.
  - The old Pull button still runs verify/confirm; Stage 4 removes them.
  - Retrospective stage-work assessment: **0.25**.

## Stage 4 - Retire the old steps

**Original estimate: 0.15** (unchanged)

- Changes:
  - The card is now Connect, **Pull new messages** (shown once this key is connected), the replace prompt, one status line and the pull summary. Removed: register, approve, the import step text, the trust list and its button, Check again, and the `.local-pull-keys` styles.
  - Removed relay actions `prepare_identity`, `create_identity`, `verify_list` and `confirm`. Also removed `LocalDevelopmentRemote::prepareIdentity()`, `createIdentity()` and `acknowledge()`, and their `PATHS` (`POST /api/prepare_identity`, `POST /api/create_identity`, `POST /api/private_messages/history_sync/acknowledge`). Nothing is written to the source any more.
  - `/local/` no longer loads `private_message_history_crypto.js`, which only verify used.
  - Pull runs the same pass as Connect without the window.
  - Concepts:
    - Rewrote *Messages pull* in `local_development_import.md`.
    - New `docs/concepts/key_handoff.md`.
    - The handoff note went in `docs/reference/key_backups.md`; the plan's `key_backups.md` is that reference page, and there is no concept of that name.
- Verification:
  - `LocalDevelopmentImportTest`: the relay test now checks the guards with `key_status`, and that the four retired actions answer 400 "Unknown action." without calling the source. The rate-limit failure case moved from `acknowledge` to `export`.
  - Smoke set plus `LocalDevelopmentImportTest` and `LocalMessagePullTest`: 507 run, 507 passed.
  - `key_handoff.mjs` passes.
  - `git grep` finds no leftovers outside the unrelated lobby flow, and the relative links in the changed docs resolve.
- Notes:
  - **Deviation:** `LocalMessagePull` **still imports transfers**. The plan assumed every message decrypts directly with the handed-over key. But mail from before that key existed reaches it on zenmemes.com only through history-sync transfers, and local Messages already serves pulled transfers for the viewer's key (`PrivateMessageMailboxService` → `transfersFor`), with no verify or confirm needed. Dropping them would make older mail unreadable here. What retired is the verify/confirm round trip, which only reported coverage back to the source.
  - Retrospective stage-work assessment: **0.15**.
