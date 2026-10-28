> **Feature plan:** [Step 1](./invite_signing_readiness_step1_solution_assessment.md) · [Step 2](./invite_signing_readiness_step2_feature_description.md) · [Step 3](./invite_signing_readiness_step3_development_plan.md) · [Step 4](./invite_signing_readiness_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/invite_signing_readiness; standing P2 enrollment; [ledger](../effort_estimation_trial.md)
Extended estimates: enabled
Status: Step 1 Option A approved; implementation not authorized

**Cycle estimate: 0.50**

## Original Query

The first couple of times I tried to generate an invite code, I got this message:

Detached signature verification failed: signature_verification_failed

I then returned to the Account page, which must have refreshed something.

After that, I went back to the Invite page, and it worked that time.

This is VERY VERY VERY VERY VERY VERY BAD. Please write Step 1 to fix it.

## Problem and evidence

An existing member must be able to generate an invitation on the first attempt without visiting Account to repair hidden signing state.

Observed source gap: Invitation preparation uses the authenticated server identity, while signing reads the current browser key; Invite does not compare them or await session authentication as part of its action readiness. Its default readiness path can start identity-hint synchronization without awaiting it. Account entry runs additional identity refresh and authentication behavior. Existing compose signing has an author-mismatch guard that Invite lacks.

Uncertainty: This makes stale session/key state and authentication timing credible causes, not a confirmed diagnosis of the reported incident. The generic verifier error does not distinguish identity mismatch from other signature failures. Prior QR acceptance authenticated through Account first, masking the reported entry condition; those results do not establish first-attempt reliability.

## Option A — Establish a consistent signing identity before preparing (recommended)

**Estimate: 0.50**

Make invitation issuance establish that the active browser key and authenticated issuer agree, then keep preparation and signing tied to that identity. Reconcile recoverable session state within Invite; detect an identity change before sending an invalid signature.

- Benefit: Prevents the suspected first-attempt failure and the Account detour while preserving existing keys, approvals and signature verification.
- Driver/uncertainty: Reproduce the reported path with stale sessions, delayed authentication and cross-tab key changes; the precise failure mechanism remains unconfirmed. Reuse shared readiness/authentication where appropriate and regression-check affected consumers, including invitation revocation.

## Option B — Recover after a verified identity mismatch

**Estimate: 0.35**

Keep the existing initial attempt, but identify an issuer/key mismatch, reconcile authentication and retry preparation once within Invite. Give an actionable in-place failure if recovery cannot complete.

- Benefit: Smaller change that can remove the manual Account visit.
- Tradeoff: Leaves the initial race and failed work in place; retry must be limited to a known pre-write rejection, never an ambiguous response or every generic signature failure. Diagnostic/reproduction uncertainty remains.

## Recommendation and completion boundary

Choose A. First reproduce and distinguish the actual failure using real signing/server verification; retain a regression that fails on the current code. Test entry directly from the ordinary site and restored sessions without Account setup, immediate clicks, delayed authentication, stale identity metadata, key changes during preparation and invalid signatures. If the cause differs materially, revise this assessment before implementing a speculative identity fix.

Outcome: The user's existing valid approved identity generates one usable invitation/link/QR on the first attempt; a separate recipient redeems it. Repair recoverable session state in place. Preserve the selected destination and existing key/approval; provide an actionable retry or restore-identity path when automatic recovery is impossible. No automatic key replacement, approval bypass, weakened verification, endless retries or duplicate issuance. Preserve permission rejection when the current identity is not approved. Verify public and members-only flows and unaffected shared signing consumers.

Dependency inventory: Zero proposed external additions/updates; no schema changes planned. Documentation scope includes cycle artifacts, plan index and ledger; todo.txt stays untouched. This is a distinct reliability follow-up to [QR theming](../qr_theme/qr_theme_step4_implementation_summary.md), not a claim that coloring caused signature failures.

## Study checkpoint and request disposition

Step 1 handoff: 2026-10-27 UTC; scope v1/A, baseline 89a9abbe044a39240a30ab7787977ce735050762, R1/P2; whole-cycle 0.50, alternative B/v1 0.35 (not additive). All three frozen hashes reverified. Codex single exposed rater after source inspection and prior QR test history; pristine pre-inspection intake missing, not reconstructed. Includes reproduction, planning, implementation, required verification/documentation and ordinary correction. No measured labor or accepted outcome; same stable ID across worktrees.
Follow-up requests: User reported poor QR visual fit and asked about light-on-dark codes; address separately after this plan. They do not expand this signing cycle.
Approval: User supplied Approved Step 1 on 2026-10-27; Option A advances to Step 2. No application changes or commits in this cycle.

*— Codex · 2026-10-27 UTC*

Evidence update, 2026-10-27 UTC: A subsequently authorized isolated diagnostic [reproduced the exact failure/Account-recovery sequence](./invite_signing_readiness_reproduction.txt) in public and members-only modes. Re-authenticating the saved key on Invite also repairs it without a key change. The earlier uncertainty is preserved as historical context; session/key mismatch is now demonstrated, while the origin of the user's stale session remains unconfirmed. Recommended Option A and 0.50 projection remain unchanged.

*— Revised by Codex · 2026-10-27 UTC*
