> **Feature plan:** [Step 1](restore_invitation_prompt_step1_solution_assessment.md) · [Step 2](restore_invitation_prompt_step2_feature_description.md) · Step 3 · [Step 4](restore_invitation_prompt_step4_implementation_summary.md)
Debug mode: enabled
Effort study: railspike-effort / v3/restore_invitation_prompt; [ledger](../effort_estimation_trial.md#restore-invitation-prompt)
Extended estimates: enabled
**Cycle estimate: 0.75**

## Completion Contract

- Deliver approved v2/C: untargeted Lobby Username field and Redeem action, same-key initial naming despite disabled later renaming, and Account recovery for accepted guests. Named identities and automatic targeted entry remain intact.
- Preserve entered names, keys, posts and grants through validation, lost responses, competing requests and refresh failures; replay the reported no-name-choice/no-later-action condition with automatic keys on/off and renaming disabled. Verify names and fingerprints after reload and ordinary CLI rebuild.
- Release only after focused API/browser/shared-consumer and phone/desktop/theme checks. Actual user site/browser is unspecified: provide replay instructions and report live recovery unverified. No deployment, merge, dependencies or schema changes; return to Step 2 if those become necessary.

## Key Risks

- **High risk:** A flag exception could allow unlimited renaming. Inspected IdentityBootstrapRecordParser and ProfileNames::history supply original name (including legacy default `guest`) and verified history. Recheck eligibility under the write lock at prepare/finalize; prior signed naming, including a return to guest, closes the exception. Stage 1 must prove this before UI integration.
- **High risk:** Naming can commit before redemption fails, and current name finalization cannot recover after its prepared token is deleted. Recover exact signed committed naming before rejecting an expired/missing token; verify current name and repair stale projections without another write. Retain pending signed request per identity for retry. Stage 2 proves the recovery before dependent work.
- **High risk:** Shared identity setup or late visibility could replace a key or shift controls. Reuse existing initialization and editor; decide locally knowable state before paint, keep server HTML viewer-independent, and treat local hints only as UI hints. Stage 3 must validate guest/named/restored state and stale-hint failure without late control insertion; unresolved static-rendering or identity races block Stage 4.

Stage estimates below cover stage work; all stages target ≤1 hour. Replan oversized work before execution. S5 owns shared release checks.

## Stage 1 — Initial-name authority (S1)

**Estimate: 0.12**

- Goal/changes: One initial signed name for original guests; subsequent renames obey the flag.
- Dependencies: Approved Step 3 and planning commit.
- Canonical contracts: LocalWriteService naming prepare/finalize; loadIdentity and ProfileNames::history; internal eligibility helper, unchanged public signatures.
- Verification: Flag states, legacy/named/renamed-back guests, forgery and competing requests.
- Risks/estimate details: Canonical eligibility drives effort; reject local-only claims and stale acceptance before S2/S3.

## Stage 2 — Naming retry (S2)

**Estimate: 0.15**

- Goal/changes: Recover exact committed naming and stale projections; retain pending signed request through reload, bound to its key.
- Dependencies: S1.
- Canonical contracts: Profile-name finalizer; browser_signing.js saveRegisteredName(chosen, root, statusNode); existing commit/rebuild patterns.
- Verification: Lost response, missing/expired prepare token, refresh failure, forged replay, newer name and identity switch; one canonical update.
- Risks/estimate details: Partial success drives effort; never overwrite newer state. Refuse mutation if retry storage fails; prove recovery before S3/S4.

## Stage 3 — Account recovery (S3)

**Estimate: 0.12**

- Goal/changes: Open existing inline naming to eligible guests with renaming off, preserving key/posts/approval.
- Dependencies: S1–S2.
- Canonical contracts: account_key.php editor/initialization; browser_signing.js saved-state/naming helpers.
- Verification: Approved/pending/restored guests, named keys, flags, stale hints, cancel/save/retry and first paint.
- Risks/estimate details: Shared UI state drives effort; no server personalization or replacement key. Resolve visibility/races before S4.

## Stage 4 — Name and redeem (S4)

**Estimate: 0.15**

- Goal/changes: Username above Redeem; validate, create/reuse key, save naming, redeem; retain input/progress on failure.
- Dependencies: S1–S3 passed.
- Canonical contracts: lobby.php, invite_redemption.js, shared naming helper exposed without duplicated protocol; existing invitation endpoints.
- Verification: Fresh/automatic/published guest/named keys; invalid/abandoned input, partial success, lost acceptance, retry and targeted entry.
- Risks/estimate details: Asynchronous ordering drives effort; bind to initiating identity, prevent duplicate clicks, never imply saved naming rolled back.

## Stage 5 — Release proof (S5)

**Estimate: 0.14**

- Goal/changes: Replay reported failure; replace optional-dialog tests with field/name assertions; complete guidance and records.
- Dependencies: S1–S4.
- Canonical contracts: Existing invitation browser fixtures, WriteApiSmokeTest, InvitationIssuanceTest, BrowserSigningNormalizationTest and Account tests; invitation/API/flag docs, UI audit, concepts and cycle ledger.
- Verification: Public/private, both flags, single/capped invitations, ordinary rebuild, profile/author/activity labels and retained key/posts/approval; affected themes at phone/desktop, overflow/expanded/error states; links/whitespace and zero dependency/schema delta.
- Risks/estimate details: Shared coverage drives effort; local passes do not prove live recovery. Include operator replay guidance and explicit unverified limits.

## Execution and checkpoint

Authorization: User said “approved step 2”; scope v2/C unchanged. Await Approved Step 3. Then create a new feature branch from this reviewed baseline (no branch reuse requested), organize this cycle's plans, make its planning-only commit, create Step 4 summary, and commit each verified stage with its summary before the next. Stages target at most one hour each; split/replan before execution if that is unrealistic. No implementation now.
Concepts: Update invitation lifecycle entry guidance; add initial naming versus renaming inventory in S5. No prompt restoration, bot controls or unrelated Account cleanup.
Dependencies: Zero external additions/updates; existing stack and test tools only. Records remain Key: value; no migration/deployment prerequisite anticipated.
Checkpoint: 2026-11-01 UTC; v3/restore_invitation_prompt, v2/C, baseline 767a8a2ba8270e29269fd9d2f6d2e707bff8ffa4, R1/P2. Whole-cycle 0.75 revises 0.65 for durable signed-name retry and cross-tab recovery, not added scope; original A 0.55 and C 0.65 preserved. S1–S5 original stage-work estimates above exclude shared planning; S5 owns shared checks. Non-additive judgments, not hours. Frozen hashes verified; Codex single exposed rater; missing pristine intake retained. No measured labor or accepted outcome; all planning remains uncommitted.

*— Codex · 2026-11-01 UTC*
