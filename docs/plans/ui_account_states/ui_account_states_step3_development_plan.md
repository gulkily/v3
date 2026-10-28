> **Feature plan:** [Step 2](./ui_account_states_step2_feature_description.md) · [Step 3](./ui_account_states_step3_development_plan.md) · [Step 4](./ui_account_states_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_account_states` ([ledger](../effort_estimation_trial.md#cycle-v3ui_account_states)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.95**

Step 2 approved 2026-10-28 ("Approved Step 2"), including the key-model revision; no scope added since. Branch `feat/ui-account-states` from `main`. Reference mockup (temporary, private): the six-screen canvas reviewed with the user on 2026-10-28.

## Completion Contract

- Normal entry: `/account/key/` on ZenMemes, Chouse, MIT Rap Club and QDB as guest (automatic guest key on and off), unapproved key and approved key, phone and desktop, including an approved-members-only configuration.
- Outcome: the right state card on first paint; naming, renaming, saving a copy, using a key from another device, Advanced and Remove this key all work without browser prompts.
- Recovery: inline Retry for failed publish and rename; Undo after Remove; Cancel or Escape closes the sheet and dialog with no side effects; without JavaScript the Link a public key form posts as today.
- Deployment: normal deploy; `./v3 build-static` is not needed (the account page is not pre-built).
- Release: focused tests per stage; all permitted themes per site at 390px and 1280px in each state with the sheet and dialog open; the account, backup, approval, invite and private-site browser tests pass; one full suite with pre-existing failures recorded.

## Decisions from Step 2's open questions

- **Save-a-copy condition: approved keys only.** Private messaging is limited to approved keys ([private messaging](../../architecture/private_messaging.md)), so approval covers both re-approval and message loss.
- **Join hint: checked after Continue or on load, not while typing.** A new read-only text endpoint `GET /api/get_username?username=` returns `Username-Token`, `Approved-Keys: <count>` in `Key: value` form with `no-store`, from the existing username index. On approved-members-only sites it stays behind the normal access gate, so unapproved visitors see no hint rather than learning which names approved members use.
- **Key state before paint:** the server already knows the signed-in key (`viewerProfile` with `is_approved`). An inline script that runs before first paint picks Guest (no saved key, or the automatic `guest` key with no chosen name), Waiting or Approved; it uses the server's approval only when the server's identity matches the browser's stored fingerprint. Otherwise the card shows the name and key without a chip and fetches `/api/get_profile` for this key; showing the chip once known is a real state change, not a layout correction.

## Key Risks

- **High risk:** existing set-up, invite and private-site flows depend on today's buttons and the name prompt. Impact: sign-in or invitation regressions on all sites. Validation: Stage 1 and Stage 2 each run `account_key_approval`, `testing_self_approval`, `invite_zero_click`, `invite_signing_readiness`, `key_backup_ui`, `access_history`, plus `AccountKeyPageHooksTest` and the account checks in `LocalAppSmokeTest`. Mitigation: keep every `data-action` hook and the `browser_signing.js` handlers; change only how the name is collected and where controls sit.
- **Wrong state on first paint.** Validation: per-state cold-load browser test with a paint-time screenshot. Mitigation: the pre-paint script described above; no state card is visible until it runs, with a no-JavaScript fallback to today's form.
- **The new endpoint leaks private-site names.** Validation: a PHP test that an unauthenticated request on a members-only site is denied. Mitigation: no allowlist entry.
- **Copy misstates the key model.** Mitigation: Stage 5 reviews every string against the profile contract and `authentication_modes.md`; the copy record lists each.

## Stage 1 - One page with three states

**Estimate: 0.25**

- Goal: all sites show Guest, Waiting or Approved on first paint, with the everyday rows, Advanced and the page title "Account"; behavior of each control unchanged.
- Dependencies: none.
- Expected changes: `templates/pages/account_key.php` restructured (state cards, rows, Advanced holding today's Keys, Link a public key and Storage and privacy content); the pre-paint state script; `account.css`/`identity.css`; Pending/Approved chips reused; `/user/<name>` link for approved keys; hooks `data-tone`, starfield slot, `night` class, `data-sky`; page title in `ComposeAndAccountKeyController`. Back up, Restore and Remove rows open today's controls until Stages 3–4.
- Verification approach: new `tests/browser/account_states.mjs` (cold load per state, guest with automatic key on and off, approval from server vs `/api/get_profile`, no-JavaScript fallback); update `AccountKeyPageHooksTest`; run the browser tests listed under Key Risks.
- Estimate details (extended mode only): driver: template rewrite on all sites plus test churn; uncertainty: medium.
- Risks or open questions:
  - Impact: hidden hooks break other flows.
  - Early warning / validation: the existing browser tests in this stage.
  - Mitigation: move markup, keep hooks.
- Canonical components/API contracts touched: `account_key.php`, account page inline script, `viewerProfile`.

## Stage 2 - Naming without prompts

**Estimate: 0.20**

- Goal: a guest names the key with the field and Continue; the Name row renames in place (when username changes are enabled) and says posts move to the new name; the join hint appears for names with approved keys; failures show inline with Retry.
- Dependencies: Stage 1.
- Expected changes: `browser_signing.js` takes the name from the page instead of `promptForAccountUsername`/`window.prompt` (other callers of the prompt keep a fallback); new `/api/get_username` endpoint with a PHP test and a `docs/reference/agent_api.md` entry; inline error and Retry in the card and Name row.
- Verification approach: browser test: name, rename, join hint shown and absent, failure and Retry, Escape cancels rename; PHP tests for the endpoint (public, members-only denied, unknown name); invite and approval browser tests.
- Estimate details (extended mode only): driver: shared prompt helper and its other callers; uncertainty: medium.
- Risks or open questions:
  - Impact: invite redemption still prompts or skips naming.
  - Early warning / validation: `invite_zero_click`, `invite_signing_readiness`.
  - Mitigation: only the account page passes a name; other flows keep current behavior.
- Canonical components/API contracts touched: `browser_signing.js` naming, `prepare_profile_name`/`finalize_profile_name` (unchanged), new `/api/get_username`.

## Stage 3 - Save a copy and use a key from another device

**Estimate: 0.20**

- Goal: the save-a-copy sheet (passphrase, confirmation, strength hint, Text file or Starfield image, one Download), the dated per-key record, the prompt for approved keys, and "Use a key from another device" for restore.
- Dependencies: Stage 1.
- Expected changes: `key_backup_ui.js` drives a native `<dialog>` sheet with a format choice and one Download; a small built-in strength hint (length and variety, no library); `key_backup_status.js` stores the date with the existing per-key flag and keeps the Messages reminder; restore keeps today's file and passphrase flow in its own sheet; `docs/reference/key_backups.md` updated.
- Verification approach: `key_backup_ui.mjs` (both formats, restore, rollback) updated for the sheet; `key_backup.cjs`; prompt shown only for approved keys without a copy; "Copy of this key saved {date}" after download; Escape and Cancel close without side effects.
- Estimate details (extended mode only): driver: existing backup tests with real downloads; uncertainty: low-medium.
- Risks or open questions:
  - Impact: a download no longer verifies before starting.
  - Early warning / validation: the existing verification assertions in `key_backup_ui.mjs`.
  - Mitigation: reuse the current download function unchanged.
- Canonical components/API contracts touched: `ForumKeyBackupUI`, `ForumKeyBackupStatus`.

## Stage 4 - Remove this key

**Estimate: 0.08**

- Goal: one Remove this key action with a confirmation that says posts stay under the name and a new key would wait for approval; "Save a copy first" for approved keys; Undo stays.
- Dependencies: Stages 1 and 3.
- Expected changes: confirmation `<dialog>`; the two duplicate clear buttons become one; members-only redirect to the Lobby kept.
- Verification approach: browser test: cancel, save first, confirm, Undo; members-only redirect; `access_history`.
- Estimate details (extended mode only): driver: small; uncertainty: low.
- Risks or open questions:
  - Impact: Undo lost.
  - Early warning / validation: Undo in the test.
  - Mitigation: reuse `clearBrowserIdentity` and its saved copy.
- Canonical components/API contracts touched: `clear-browser-identity`, `undo-clear-browser-key`.

## Stage 5 - Sites, themes, copy and closeout

**Estimate: 0.17**

- Goal: confirm every site and theme, record copy, close the cycle.
- Dependencies: Stages 1–4.
- Expected changes: theme fixes where screenshots show problems; `ui_copy_audit_checklist.md` entry with every new string; no navigation destination change, so `theme-nav-icons.css` is untouched (recorded); checklist ticks; Step 4 closeout and ledger.
- Verification approach: screenshots of each state, the sheet and the dialog at 390px and 1280px in every permitted theme per site (ZenMemes 14, Chouse 15, QDB 15, MIT Rap Club 15); guest JavaScript on/off; one full suite. Real-iPhone check listed as pending for the user.
- Estimate details (extended mode only): driver: four sites × themes; uncertainty: low-medium.
- Risks or open questions:
  - Impact: pre-existing failures obscure results.
  - Early warning / validation: compare with `main`.
  - Mitigation: record, don't chase.
- Canonical components/API contracts touched: theme variables.

## Execution and study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 3 handoff | 2026-10-28 | 0.95 (reaffirmed) | v1 / R1 / P2, baseline `main` `cba7d54f9` | Claude, single exposed rater; also inspected `/api/get_profile`, the messaging eligibility rule and the absence of a username API | The new endpoint adds work; approval-only saving and reuse of the profile API offset it |

Stage estimates are separate annotations and are not summed. Earlier snapshots: [Step 2](./ui_account_states_step2_feature_description.md#approval-and-study-checkpoint).

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*
