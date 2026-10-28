> **Feature plan:** [Step 1](./ui_first_post_identity_step1_solution_assessment.md) · [Step 2](./ui_first_post_identity_step2_feature_description.md) · [Step 3](./ui_first_post_identity_step3_development_plan.md) · [Step 4](./ui_first_post_identity_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_first_post_identity` ([ledger](../effort_estimation_trial.md#cycle-v3ui_first_post_identity)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.85**

## Step 4 entry

- Step 3 approved 2026-10-28 ("approved step 3"). Branch `feat/ui-first-post-identity` from `main` at `74d044c1f`; planning commit `33a76fb31`.
- Whole-cycle projection **0.85 reaffirmed** at entry; scope v1.2 (G1) / R1 / P2 unchanged. Claude, single exposed rater. Stage originals are in the [Step 3 plan](./ui_first_post_identity_step3_development_plan.md); copied below without re-rating.
- Uncommitted on entry and carried on the branch: the ledger's pending rows for this cycle (closeout in Stage 8) and two checklist notes (cycle 3 pause, recorded as separate work in Step 1; cycle 5 start, updated in Stage 7). Their links were repaired for the new folder.

## Stage plan

| Stage | Original stage work | Status |
| --- | --- | --- |
| 1 - Keys stay local; no dialogs | 0.15 | done |
| 2 - One registered check; guests shown as guests | 0.15 | pending |
| 3 - Composer name row on first paint | 0.15 | pending |
| 4 - Change the name from the composer | 0.12 | pending |
| 5 - Creating your account, failures and Retry | 0.12 | pending |
| 6 - Other signed paths and members-only sites | 0.10 | pending |
| 7 - Sites, themes, copy and docs | 0.10 | pending |
| 8 - Acceptance and closeout | 0.06 | pending |

Stage estimates are not summed.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Stage 1 - Keys stay local; no dialogs

**Original estimate: 0.15**

**Retrospective: 0.17**

- Changes:
  - `public/assets/browser_signing.js`: the name prompt and its "Continue with 'guest'?" confirm are gone. A new key takes the stored name, an invitation's temporary name, or `guest`. The Account page's prompt fallbacks (no name field; no inline name editor) are gone too, since its template always has both.
  - Automatic guest keys are created with `publishPublicKey: false` in every case. The "Publish guest key immediately" preference is no longer read or written; a leftover stored value is inert.
  - The background check on Like pages (`ensureStoredIdentityReady`) now skips any key without a matching published fingerprint, so it only verifies registered keys; their readiness is `not_applicable` and the first Like registers.
  - `public/assets/private_site_auth.js`: on public sites, the automatic sign-in on page load skips a key that was never registered. This was the second reader-side registration path (found by the new browser test). Members-only sites are unchanged; Stage 6 covers them.
  - `templates/pages/account_key.php`: the setting and its caption are removed.
  - The debug-only `username_prompt_ms` timing field is removed from both timing logs.
- Verification:
  - New `tests/browser/first_post_identity.mjs` (ZenMemes, automatic guest keys on, 390px): reading the board and a thread creates a local `guest` key and sends no `prepare_identity`, `create_identity` or `link_identity`; the repository head is unchanged. The first Like sends exactly one `prepare_identity` and one `create_identity`, then votes. After a reload, the prewarm reports `ready` and a second Like registers nothing. Any dialog fails the test. **Passed.**
  - `php tests/run.php BrowserSigningNormalizationTest`: 81/81 passed. Updated: no prompt in concurrent preparation; Account setup reads the name field; transient-retry uses a stored name; prewarm of a registered key unknown to the server republishes it. Added: prewarm leaves an unregistered key local. Removed: two tests of the publication preference.
  - `php tests/run.php PrivateSiteAuthTest`: 11/11 passed. Fixtures for registered keys now carry the published fingerprint. Added: an unregistered key on a public resume page sends nothing (fails without the fix).
  - `AccountKeyPageHooksTest` 6/6 passed. `AuthNavigationTest` 3/3 passed. `InvitationIssuanceTest` 10/10 passed. `LocalAppSmokeTest` 153/154 passed; the failure (`testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp`: a method that does not exist) is unrelated to this change.
  - Browser tests passed: `signed_reactions`, `account_states`, `board_compose_focus`, `reply_compose_focus`, `invite_redemption.cjs`, `invite_signing_readiness`, `invitation_issuance.cjs`, `invite_signer_identity.cjs`, `post_retraction`, `fastmod`, `high_traffic_control`, `same_author_collapse`, `key_backup_ui` and `private_message_compose.cjs`. `invite_zero_click` failed once, then passed on rerun (flaky).
  - Fail on `main` too, so not caused by this change: `account_key_approval` (timeout) and `reply_continuation` (timeout at line 147).
  - Registration timing: a ZenMemes-sized copy (`repository.tar.gz`, 1,619 posts, 1,595 commits) was rebuilt in the scratchpad and run on a local PHP server. Three first registrations took 314, 174 and 173 ms (prepare 20–77, sign 3–9, create 150–228). This was local, with no network or lock contention, so it is not phone evidence.
- Notes:
  - The Account page's Continue still registers a new named key. Stage 2 makes it local.
  - The `forum_pki_compose_prompt_cancelled` key no longer has a writer. It stays in backup and clear handling so older backups restore unchanged.
  - The production canary's simulated name prompt (`tests/browser/OpenPgpProductionCanaryTest.mjs`) is left as defensive coverage.
  - Retrospective 0.17 against the original 0.15, because of the extra public sign-in path and its fixture updates.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*
