> **Feature plan:** [Step 1](./ui_first_post_identity_step1_solution_assessment.md) · [Step 2](./ui_first_post_identity_step2_feature_description.md) · [Step 3](./ui_first_post_identity_step3_development_plan.md) · [Step 4](./ui_first_post_identity_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_first_post_identity` ([ledger](../effort_estimation_trial.md#cycle-v3ui_first_post_identity)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.85**

Step 2 approved 2026-10-28 with both defaults: the name row on every site, and the "Publish guest key immediately" setting removed.

## Completion Contract

- Normal entry: any signed composer (new thread, reply, board quick compose, Forte compose panel and reply tree) or Like button, as a new visitor with JavaScript, on phone and desktop, on every site.
- End-to-end outcome: the first post, reply or Like goes through under the chosen name or `guest` with no browser dialog, after one registration. A visitor who only reads sends no registration and causes no commit.
- Required recovery: a failed key creation or registration shows a message and Retry beside the composer or Like, with the draft kept; Post anonymously stays.
- External verification: a real-iPhone check on ZenMemes as a new visitor, done by the user. It stays **pending** until then; emulation doesn't count.
- Release condition: the success criteria from Step 2, the checklist's every-cycle checks, one full suite run, and a branch from `main` that is not merged without the user asking.

## Key Risks

- **High risk:** code that treats any saved key as published (account state, profile link, toolbar name, Outbox, private-site sign-in, messages). Impact: wrong "waiting for approval" state or errors for guests. Early validation: Stage 2 browser tests with a local, unregistered key on each surface. Mitigation: one shared registered check, used everywhere.
- **High risk:** members-only sites. Impact: a visitor can't sign in or request access. Early validation: Stage 6 Lobby flow with no key. Mitigation: sign-in and invitations keep registering first.
- First-action wait on a phone. Impact: still feels slow. Early validation: Stage 1 times registration on ZenMemes-sized data. Mitigation: the visible state (Stage 5); G2 follows.
- Composer row wrong on first paint. Impact: a flash of "guest" for a named key. Early validation: cold-load browser test per state in Stage 3. Mitigation: decide the row before paint from browser storage, as cycle 4's account card does.

## Stage 1 - Keys stay local; no dialogs

**Estimate: 0.15**

- Goal: no prompt or confirm anywhere; no registration until an action needs one.
- Dependencies: none.
- Expected changes: identity preparation uses the stored name or `guest` instead of prompting; the prompt helpers go. Automatic guest creation never publishes. The background check on Like pages verifies only registered keys. The Account "Publish guest key immediately" setting and its stored preference go.
- Verification approach: browser tests: reading the board and a thread with automatic guest keys on sends no `prepare_identity` or `create_identity` and leaves the repository head unchanged; a first Like registers once, then votes; no dialog fires (dialog handler fails the test). Time `create_identity` on a ZenMemes-sized copy.
- Estimate details: driver: the prompt's many callers and the background check; uncertainty: tests that expect background publishing.
- Risks or open questions:
  - Impact: existing tests assert the prompt or background publishing. Early warning: focused identity and reaction tests. Mitigation: update them to the new rule and record it.
- Canonical components/API contracts touched: `prepareReadyIdentity`, `scheduleAutomaticGuestIdentity`, `scheduleIdentityPrewarm` / `ensureStoredIdentityReady` in `browser_signing.js`; `account_key.php` setting row.

## Stage 2 - One registered check; guests shown as guests

**Estimate: 0.15**

- Goal: every surface treats a local, unregistered key as a guest under its name.
- Dependencies: Stage 1.
- Expected changes: a shared `isRegisteredIdentity()` helper (stored published fingerprint matches, or the server confirms). The account page shows "You're posting as mouse" (guest card) for an unregistered named key. Continue there replaces the local key with one under that name, sending nothing; a registered key renames as in cycle 4. The profile link stays hidden and the toolbar shows the name without a profile link.
- Verification approach: browser tests for no key, local `guest`, local named and registered keys on Account and in the toolbar, cold load, phone and desktop.
- Estimate details: driver: account state on first paint; uncertainty: identity hint syncing for unregistered keys.
- Risks or open questions:
  - Impact: the identity hint cookie points at an unknown key. Early warning: profile/hint tests. Mitigation: skip hint sync until registered.
- Canonical components/API contracts touched: account page state script (`accountState`), `renderSavedState`, `syncIdentityHint`, toolbar identity status.

## Stage 3 - Composer name row on first paint

**Estimate: 0.15**

- Goal: "Post as guest · Change" in every signed composer, correct before paint.
- Dependencies: Stage 2.
- Expected changes: one shared row partial used by `thread_compose_form.php`, `reply_form.php` and Forte's compose panel and reply tree. Before paint, a small inline script sets the name from browser storage; the row is hidden without JavaScript or when posting anonymously only. Theme styles for the row.
- Verification approach: cold-load browser tests per key state on each composer; server HTML identical across viewers; no-JavaScript composers unchanged.
- Estimate details: driver: five composer placements and Forte's layout; uncertainty: the quick compose's compact layout on phones.
- Risks or open questions:
  - Impact: a layout shift in compact composers. Early warning: 390px screenshots. Mitigation: reserve the row's height.
- Canonical components/API contracts touched: composer partials, new `partials/compose_identity_row.php`, theme CSS.

## Stage 4 - Change the name from the composer

**Estimate: 0.12**

- Goal: Change opens an inline name field; Done applies it without leaving the composer.
- Dependencies: Stage 3.
- Expected changes: the row's inline editor reuses Account's name rules. An unregistered key is replaced locally, and a registered key renames through cycle 4's path. Errors show inline in the row; the draft is untouched.
- Verification approach: browser tests: rename while unregistered sends nothing, then the first post registers under the new name; rename while registered saves the name; a name error shows in the row.
- Estimate details: driver: sharing cycle 4's rename code; uncertainty: username changes disabled on some sites (the registered rename is hidden there).
- Risks or open questions:
  - Impact: replacing a key that already has an Outbox item signed by it. Early warning: Outbox test. Mitigation: only replace when nothing is queued; otherwise rename after registering.
- Canonical components/API contracts touched: `saveProfileName` / `renameTo` (moved to shared), `generateBrowserKey`.

## Stage 5 - Creating your account, failures and Retry

**Estimate: 0.12**

- Goal: a first action shows "Creating your account…" and recovers from failures.
- Dependencies: Stage 1.
- Expected changes: the composer status and Like feedback show the creation state during registration. Failures (library load, key creation, registration) show a short message with Retry; the draft stays; Post anonymously stays.
- Verification approach: browser tests with registration forced to fail, then Retry succeeds; draft retained; Like failure recovers.
- Estimate details: driver: three failure points and two surfaces; uncertainty: existing status wording asserted in tests.
- Risks or open questions:
  - Impact: a duplicate post if Retry runs after a slow success. Early warning: retry test with a delayed response. Mitigation: Retry re-checks registration and reuses the prepared post.
- Canonical components/API contracts touched: composer `setStatus`, `thread_reactions.js` feedback, `classifyIdentityBootstrapFailure`.

## Stage 6 - Other signed paths and members-only sites

**Estimate: 0.10**

- Goal: messages, invitations, Outbox, operator tools and private-site sign-in work with a local key and no dialogs.
- Dependencies: Stages 1–2.
- Expected changes: these paths register first as today, without prompting; Outbox sending registers before the first send.
- Verification approach: existing browser tests for messages, invitations, Outbox and the Lobby flow, run with no key and with a local unregistered key.
- Estimate details: driver: number of paths; uncertainty: Lobby flow assumptions.
- Risks or open questions:
  - Impact: members-only access breaks. Early warning: Lobby test first in this stage. Mitigation: keep sign-in's explicit registration.
- Canonical components/API contracts touched: `outbox_sender.js`, `private_site_auth.js`, `private_message_compose.js`, `invite_redemption.js`.

## Stage 7 - Sites, themes, copy and docs

**Estimate: 0.10**

- Goal: every site and theme renders the row and states correctly; docs match behavior.
- Dependencies: Stages 3–6.
- Expected changes: theme fixes; copy audit entry; `docs/reference/authentication_modes.md` updated (no background publishing; registration on first action); checklist cycle 5 updated.
- Verification approach: all four sites × 13 themes plus Auto on phone and desktop for the composer row and creation state; copy assertions; one full suite run.
- Estimate details: driver: the theme matrix; uncertainty: Forte themes.
- Risks or open questions: none beyond the theme matrix.
- Canonical components/API contracts touched: theme CSS, `ui_copy_audit_checklist.md`, `authentication_modes.md`.

## Stage 8 - Acceptance and closeout

**Estimate: 0.06**

- Goal: replay the reported failure and record the outcome.
- Dependencies: Stage 7.
- Expected changes: Step 4 summary closeout, ledger closeout row.
- Verification approach: the phone-sized browser run on ZenMemes as a new visitor (write, Change name, Post, no dialogs, timed); success criteria checked one by one. The real-iPhone check is handed to the user and stays pending until reported.
- Estimate details: driver: evidence gathering; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: none.

## Execution and study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 3 handoff | 2026-10-28 | 0.85 (reaffirmed) | v1.2 (G1) / R1 / P2, baseline `main` `74d044c1f` | Claude, single exposed rater; plus account state script and all signed-action callers | Eight stages match the Step 2 scope; stage work 0.15/0.15/0.15/0.12/0.12/0.10/0.10/0.06, not summed |

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*
