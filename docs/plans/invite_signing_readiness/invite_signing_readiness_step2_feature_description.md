> **Feature plan:** [Step 1](./invite_signing_readiness_step1_solution_assessment.md) · [Step 2](./invite_signing_readiness_step2_feature_description.md) · [Step 3](./invite_signing_readiness_step3_development_plan.md) · [Step 4](./invite_signing_readiness_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/invite_signing_readiness; standing P2 enrollment; [ledger](../effort_estimation_trial.md)
Extended estimates: enabled
Status: Steps 1–2 approved; Option A; implementation not authorized

**Cycle estimate: 0.50**

## Problem

The user encountered repeated detached-signature failures generating an invitation, then succeeded after visiting Account. Session/signing-identity disagreement is a source-supported hypothesis; the exact incident cause remains unconfirmed.

## User stories

- As an approved member, I want Generate invite to work on my first attempt so that I can invite someone without an Account-page detour.
- As a member whose browser identity or session changed, I want safe recovery within Invite so that I retain my keys, approval and intended destination.

## Core requirements

- Establish agreement between the current browser signing key and authenticated issuer before preparing an invitation. Wait for required authentication readiness rather than relying on page-start timing or identity hints as proof of authentication.
- Keep preparation and signing bound to the same identity; detect changes before final submission. Recover a known recoverable pre-write mismatch within Invite with at most one fresh preparation; stop with actionable feedback if identity keeps changing or recovery fails.
- Preserve the existing keypair, approval and destination. Never generate a replacement identity as a repair, transfer another session identity's permissions, weaken signature verification or accept an unapproved current identity.
- Produce one usable invitation and its matching link/QR per successful generation action. Prevent overlapping submission, hide stale results, and retain existing copy recovery for QR failure. Never blindly repeat issuance after an ambiguous finalization response; preserve uncertainty and a safe next action.
- Make authentication, permission and unrecoverable signing failures actionable without presenting a raw verification code as the only guidance. Recoverable session refresh requires no Account visit; genuinely missing/mismatched keys may require the existing restore-identity flow. Preserve technical failure evidence without exposing private keys or invitation secrets.

## Delivery scope and completion boundary

Work type: Application change to invitation signing readiness, its shared authentication/signing integration and focused regression coverage. Reuse existing components; zero proposed dependency additions/updates and no schema changes. Include cycle plans, debug log, plan index, study ledger and any necessary recovery-copy verification in the UI audit checklist. Leave todo.txt unchanged. QR visual revisions remain a separate unresolved follow-up.

Completion: An existing valid approved member enters Invite through ordinary navigation or a restored session, clicks Generate once and obtains a link/QR that a fresh recipient can redeem to the selected destination. Verify public and members-only operation without first opening Account. Retain a real cryptographic/server regression that demonstrates the diagnosed failure on the starting revision and passes after repair. If evidence identifies a materially different cause, revise the plan before implementation; do not report the incident fixed solely from a simulated error message or unrelated happy path.

Reported-user recovery: Replay stale session/key state and delayed authentication at direct Invite entry. Recover in place when the existing key remains valid and approved; otherwise preserve input and give an explicit retry, sign-in or restore-identity action appropriate to the failure. Confirm success through one persisted invitation and recipient redemption. The user's particular browser/device is unspecified; record tested environments and keep incident-specific user confirmation distinct from automated evidence.

## Risks and mitigation

- Wrong diagnosis: Generic verification failure may have several causes. Reproduce with real signing and inspect non-secret identity/timing evidence first; diagnosis gates repair and broader changes.
- Identity drift or permission confusion: Test stale sessions, delayed authentication and cross-tab changes before acceptance; require identity agreement and unchanged server authorization/signature checks, including negative tests.
- Duplicate or lost outcomes: Test concurrent clicks, known rejection and lost finalization responses; bound pre-write recovery, avoid ambiguous reissuance and preserve truthful recovery feedback.

## Shared component inventory

Extend the canonical Invite issue/revoke flow and result/status in invite_issuance.js and invites.php; reuse browser_signing.js identity readiness/signing and private_site_auth.js authentication rather than duplicating Account setup. Reuse Account's restore/recovery surface only for genuinely unusable saved keys. Preserve prepare/finalize invitation API authorization and server verification; adjust contracts only if the diagnosed repair requires it. Regression-check revocation and any affected shared compose, reaction, approval or redemption consumers. No parallel invitation widget or authentication system.

## User flow and success criteria

1. Open Invite with an existing browser identity; select an optional destination and generate.
2. Required session reconciliation completes in place, then the matching link/QR appears; unrecoverable failure retains input and supplies a safe next action.
3. The recipient opens the link and redeems the invitation; repeated user generation remains a separate action.

Acceptance: The reproduced first-attempt failure passes without Account preconditioning in both access modes. Stale/delayed/changing identity tests never submit a mismatched signature or issue under another identity; unrecoverable cases stop safely. Existing keys and approvals remain unchanged, successful issuance creates exactly one record, invalid signatures and unapproved identities remain rejected, and recovery does not leave a stale QR. Render changed feedback on phone/desktop and run affected shared-signing regressions.

## Study checkpoint and approval

Step 2 handoff: 2026-10-27 UTC; whole-cycle 0.50 reaffirmed for v1/A, baseline 89a9abbe044a39240a30ab7787977ce735050762, R1/P2. Failure and recovery criteria clarify the approved outcome. All frozen reference hashes reverified; same single exposed Codex rater and extended/debug modes. Earlier projections and missing pristine intake preserved; no measured labor or accepted outcome. User supplied Approved Step 2 on 2026-10-27; proceed to Step 3. Application unchanged and planning uncommitted.

*— Codex · 2026-10-27 UTC*
