> **Feature plan:** [Step 1](./invite_signing_readiness_step1_solution_assessment.md) · [Step 2](./invite_signing_readiness_step2_feature_description.md) · [Step 3](./invite_signing_readiness_step3_development_plan.md) · [Step 4](./invite_signing_readiness_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/invite_signing_readiness; standing P2 enrollment; [ledger](../effort_estimation_trial.md)
Extended estimates: enabled
Status: Steps 1–3 approved; implementation authorized

**Cycle estimate: 0.50**

## Completion Contract

From ordinary navigation/restored sessions, an existing approved member generates once without visiting Account; one matching link/QR reaches a recipient who redeems it to the intended destination. Preserve keys, approval, input and actionable failure recovery. Prove the diagnosed defect on the starting code using real signatures, then verify repair in public and members-only modes. Local evidence does not establish the unspecified user's browser/device confirmation or production deployment. QR visual revisions remain separate; no merge/push/deployment is included.

## Key Risks

- **High risk:** Wrong diagnosis or identity/permission confusion. The isolated diagnostic now reproduces the cryptographic failure; Stage 1 must retain it as a baseline regression before repair; identity hints are not authenticated sessions. A materially different cause requires replanning.
- **High risk:** Duplicate issuance after uncertain responses. Distinguish known pre-write rejection from ambiguous finalization; never automatically issue another invitation to hide uncertainty.

## Stage 1 — Reproduce direct-entry failure

**Estimate: 0.10**

- Goal: Convert the successful isolated reproduction into a durable baseline regression without Account preconditioning.
- Dependencies: Step 3 approval and planning commit.
- Expected changes: Extend isolated invitation fixtures with stale/restored session and delayed-authentication scenarios; capture non-secret issuer/signer evidence and the Account-detour control.
- Verification approach: Real browser keys and server verification; immediate Generate and cross-tab key changes. Assert the reproduced failure on baseline and retain an executable regression; do not substitute a mocked error string. Record unsupported hypotheses separately.
- Risks or open questions: Session/key mismatch is reproduced; the origin of the user’s stale state remains unknown. A materially different cause requires replanning.
- Canonical components/API contracts touched: Existing invite browser fixtures/router, invitation_issuance.cjs and relevant server signing tests; no production behavior change.
- Estimate details: S1/v1 stage work; driver is controlled session timing; uncertainty is reproducing the reported mechanism.

## Stage 2 — Reconcile issuer before preparation

**Estimate: 0.15**

- Goal: Generate successfully with the existing valid approved key after recoverable session drift.
- Dependencies: Stage 1 diagnosis verified and committed.
- Expected changes: Extend ensureInvitationIdentity(root, feedback) using shared existing-key readiness and authentication; await the required reconciliation before preparation. Preserve the server as authority for issuer permissions; avoid key generation as recovery.
- Verification approach: Turn the baseline failure into a pass; compare key/approval state before/after, count one issuance and test stale metadata, delayed/expired authentication, missing keys and an unapproved current key.
- Risks or open questions: Authentication shortcuts may trust stale page state; verify actual authenticated identity and isolate any necessary shared change.
- Canonical components/API contracts touched: invite_issuance.js, browser_signing.js and private_site_auth.js as diagnosis requires; existing prepare/authentication endpoints and authorization retained.
- Estimate details: S2/v1 stage work; driver is readiness ordering; uncertainty is shared-authentication compatibility.

## Stage 3 — Bind signing and recover safely

**Estimate: 0.15**

- Goal: Identity changes and failures cannot yield mismatched signatures, stale results or duplicate invitations.
- Dependencies: Stage 2 verified and committed.
- Expected changes: Bind prepared issuer to the signing identity through submission; permit at most one fresh preparation for a confirmed pre-write mismatch. Preserve in-flight guarding and copy fallback; distinguish retryable rejection, unusable keys and unknown finalization outcomes with concise actionable feedback.
- Verification approach: Change keys during preparation/signing, force repeated drift, duplicate clicks, invalid signatures and lost finalization responses. Assert bounded recovery, no wrong-identity submission, preserved destination and no automatic reissuance after uncertainty. Regression-check revocation.
- Risks or open questions: A generic verifier message is not a retry classification; keep server verification strict and never repair by replacing keys.
- Canonical components/API contracts touched: Invite issue/revoke/result lifecycle; existing signer and prepare/finalize contracts, extended only where the diagnosis requires.
- Estimate details: S3/v1 stage work; driver is asynchronous identity binding; uncertainty is distinguishing rejected from unknown outcomes.

## Stage 4 — Normal-flow acceptance and closeout

**Estimate: 0.15**

- Goal: Verify the reported entry/recovery path and shared-consumer compatibility.
- Dependencies: Stage 3 verified and committed.
- Expected changes: Complete focused regression coverage, implementation summary, recovery-copy audit and study ledger.
- Verification approach: Public/members-only direct Invite entry without Account, restored sessions, actual recipient redemption, one persisted issuance, unchanged keys/approval and explicit permission rejection. Check phone/desktop initial, pending, success, failure and retry states, overflow and main-action stability. Run invitation browser/PHP tests, affected BrowserSigningNormalizationTest/authentication checks and affected compose/reaction/approval/redemption regressions; syntax, links and diff checks.
- Risks or open questions: User environment remains unspecified; distinguish automated reproduction from user confirmation and report omissions. Rollback is code-only; no canonical-data migration.
- Canonical components/API contracts touched: Existing signing/invitation test harnesses; cycle documents, plan index, ledger and ui_copy_audit_checklist.md.
- Estimate details: S4/v1 stage work; owns shared acceptance/closeout; uncertainty is affected-consumer breadth.

## Execution inventory and study checkpoint

Inventory: Zero external additions/updates or schema changes; existing encoder/signing libraries reused. No todo.txt edits or QR appearance implementation.
Execution: After Approved Step 3, create feature/invite-signing-readiness; organize this cycle under docs/plans/invite_signing_readiness/ and commit approved plans/debug plus necessary index/reference fixes only. Preserve unrelated pending QR review edits outside these commits. Create the Step 4 summary afterward; verify and commit each stage with its summary before the next. Commit this cycle's accumulated ledger bookkeeping at closeout without sweeping unrelated pending entries.
Checkpoint: 2026-10-27 UTC; whole-cycle 0.50 reaffirmed independently of stage values. Same v1/A, baseline 89a9abbe044a39240a30ab7787977ce735050762, R1/P2, single exposed Codex rater; frozen hashes reverified. S1–S4 original stage-work values 0.10/0.15/0.15/0.15 are non-additive. Earlier scores and missing pristine intake retained. Four planned/zero completed; no implementation, measured labor or acceptance. Await Approved Step 3.

*— Codex · 2026-10-27 UTC*

Diagnostic experiment authorization: After discussing a 30–60 minute targeted reproduction, the user replied Approved Step 1. Proceed with that isolated diagnostic experiment only; Step 3 implementation approval remains pending. No production changes or stage completion are implied.

Diagnostic result, 2026-10-27 UTC: [Exact failure and recovery reproduced](./invite_signing_readiness_reproduction.txt) on unchanged code, in both access modes. Same-page authentication of the saved key resolves it without changing keys. Public mode also reproduces with a correct cached fingerprint; Invite can lack authentication-helper loading. Stage 1 now preserves this evidence as a durable regression. Whole-cycle 0.50 and stage estimates reaffirmed; scope unchanged, Stage 3 approval still pending.

*— Revised by Codex · 2026-10-27 UTC*

Implementation approval: User supplied Approved Step 3 on 2026-10-27. Earlier diagnostic-only approval notes remain historical.
