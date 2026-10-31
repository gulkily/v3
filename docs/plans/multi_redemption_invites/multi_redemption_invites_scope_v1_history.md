# Superseded scope v1 — multi-redemption invites

Historical record: superseded by the user’s backend-only direction on 2026-10-31 UTC. Preserves earlier requirements, stage scopes, estimates and mockups; none authorizes UI implementation.

## Step 2 snapshot

> **Feature plan:** [Step 1](./multi_redemption_invites_step1_solution_assessment.md) · [Step 2](./multi_redemption_invites_step2_feature_description.md) · [Step 3](./multi_redemption_invites_step3_development_plan.md) · Step 4 (not authorized)
Debug mode: enabled
Effort study: railspike-effort / v3/multi_redemption_invites; [P2 ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
**Cycle estimate: 0.85**

## Problem and user stories

An invitation currently admits one key. Approved Option A enables finite group admission with existing authority and recovery.

- As an issuer, I want a chosen use limit so that one link admits several recipient keys without unlimited admission.
- As a recipient, I want to retry an interrupted redemption so that I receive access without consuming another use.
- As an issuer, I want to revoke unused capacity so that further admission stops without removing earlier approvals.

## Core requirements

- **Issue:** extend Invite with a positive whole-number use limit, default 1; reject invalid/out-of-range values before issuance. Keep the fixed 30-day browser default and no expiry selector; retain API custom expiry. Count keys, not people. Preserve link/QR and destination opt-in; show the limit and sharing consequences.
- **Consume:** only a successfully accepted new recipient consumes a use. Inspection, preparation, failed attempts, already-approved entry and same-key retries consume none. Concurrent acceptance cannot exceed the signed limit, including the last slot. Preserve approval/signature requirements.
- **Stop/recover:** expiry, exhaustion and issuer revocation prevent new recipients; partial use must not prevent revocation. Accepted approvals survive later expiry/revocation. Lost-response retries reuse acceptance; identity/session recovery retains Account and Retry. Invite must let the issuer revoke the generated link without finding identifiers in raw records.
- **Preserve:** old records and requests without a limit remain single-use with their original signatures/expiry. New limits and acceptance history remain readable and verifiable; full/incremental rebuild and export/import agree on recipients and capacity. Restoring/importing a link must not reset its used count. Define old-reader deployment/restore compatibility before enabling multi-use issuance.
- **Normal entry:** preserve targeted automatic redemption, untargeted manual redemption and approved-member navigation on public/members-only sites. Show exhaustion/expiry/revocation with sender-help recovery; distinguish access denial from missing keys.

## Delivery scope and shared components

Work type: application change, with matching specs/reference, concept inventory, UI checklist and study records. Exclude dashboards, unlimited mode, expiry selectors, dependencies and deployment.

Reuse/extend: `templates/pages/invites.php` and `public/assets/invite_issuance.js` for issue/result/revoke; `templates/pages/lobby.php` and `public/assets/invite_redemption.js` for entry/recovery. Reuse `invite_navigation.js`, the existing Account/signing/auth components, shared form controls and theme styles. Extend existing prepare/finalize/inspect/redemption APIs in `IdentityApprovalAndInvitationApiController` and `LocalWriteService`; no parallel invitation service. Source views, Activity/access history and Users/Account reuse canonical records and existing approval replay/read-model presentation.

Concepts touched: [Invitation approval replay](../../concepts/approval_acceptance.md#invitation-approval-replay) changes; [Approval acceptance](../../concepts/approval_acceptance.md#approval-acceptance) and [Signed approval review](../../concepts/approval_acceptance.md#signed-approval-review) retain authority semantics. Add the proposed invitation-lifecycle inventory entry during implementation.

## Risks and flow

- Over-admission: validate serialized final acceptance and same-key retry rules before planning dependent UI; prove last-slot races.
- Lost approvals/counts on replay or restore: validate all single-use assumptions and receipt ordering first; require live/full/incremental/import parity and unchanged legacy evidence.
- Forwarded links/older software: state key-count semantics beside the choice; define supported limits and upgrade/rollback boundaries before enabling multi-use issuance. Do not infer unique people or backward support for new records.

1. Issuer opens Invite, chooses uses and optional destination, then generates/copies the link.
2. Eligible recipients redeem normally; a retry resumes the same accepted grant.
3. Issuer revokes remaining capacity; later recipients receive actionable refusal while existing approvals remain.

Completion boundary: ordinary Invite-to-Lobby-to-member access works for N distinct keys, N+1 is refused, duplicate retries add zero uses, and partial-use revocation persists through rebuild/restore. Release requires these end-to-end and race/parity checks plus phone/desktop, relevant themes, roles, invalid-limit and recovery checks; document deployment constraints separately. No specific external environment was requested.

## Approval and study checkpoint

Approval: “approved step 1” selects Option A. The 30-day change shipped separately; no new enhancement requests. Step 2 handoff: 2026-10-31 UTC; scope v1 / original baseline / R1 / P2 retained; **0.85 whole-cycle projection reaffirmed**. Three frozen R1 hashes reverified; Codex single exposed rater, no independent estimate/measured work or accepted outcome. Requirements only; no application edits/tests. Await Approved Step 2; new planning changes uncommitted.

*— Codex · 2026-10-31 UTC*

Approval: 2026-10-31 UTC — user said “approved step 2.” Step 3 drafted within Option A; implementation still awaits approval. Import parity preserves the existing content-import exclusion of invitation authority; trusted full repository restore preserves accepted grants/counts.

*— Codex · 2026-10-31 UTC*

## Step 3 snapshot

> **Feature plan:** [Step 1](./multi_redemption_invites_step1_solution_assessment.md) · [Step 2](./multi_redemption_invites_step2_feature_description.md) · [Step 3](./multi_redemption_invites_step3_development_plan.md) · Step 4 (not authorized)
Debug mode: enabled
Effort study: railspike-effort / v3/multi_redemption_invites; [ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
**Cycle estimate: 0.85**

## Completion Contract

Invite → shared link → Lobby admits N distinct eligible keys, refuses N+1, recovers same-key retries without another use, and supports partial-use revocation. Keep 30-day browser expiry, default-one uses, existing approval authority and legacy records. Full restore preserves grants/capacity; **content import continues excluding invitation authority**, not converting foreign links into local grants. Release only after race/replay/restore and public/members-only browser checks. No production deployment or old-reader rollback promise.

## Key Risks

**High risk:** over-admission, lost grants and reopened capacity. Validate shared replay/acceptance semantics before enabling issuance; serialize final checks with existing write locks and preserve acceptance receipts. **High risk:** older software assumes one recipient; upgrade all writers/readers before multi-use creation, document roll-forward recovery afterward. No rewriting signed legacy records. Inspection must not block authenticated same-key recovery solely because capacity is exhausted or later revoked.

## Stage 1 — S1: Signed issuance contract
**Estimate: 0.12**

- Goal/dependencies: fix bounded issuance semantics first; none.
- Expected changes: optional signed `Max-Redemptions`, API `max_redemptions`; absent means 1; supported integers 1–1000. Document legacy/new-reader boundary before runtime enablement.
- Verification approach: canonical round-trip, unchanged legacy bytes, malformed/duplicate/overflow limits and tampering.
- Risks: silent reinterpretation; reject invalid limits rather than clamp; no UI exposure yet.
- Canonical components/API contracts touched: `InvitationRecord`, `InvitationRecordParser`, existing prepare/finalize contracts; no new endpoints/schema planned.
- Estimate details: stage work; parsing/compatibility driver, older-reader ambiguity resolved by explicit upgrade boundary.

## Stage 2 — S2: Preserve accepted recipients
**Estimate: 0.18**

- Goal/dependencies: authoritative used-key sets and durable approvals; S1.
- Expected changes: extend `InvitationLedger` recipient lookup and `InvitationApprovalReplay`; share lifecycle decisions without replacing acceptance authority. Partial revocation remains prospective.
- Verification approach: legacy/new mixed histories, same-second ordering, duplicate recipients, receipts, expiry/revocation and full/incremental parity.
- Risks: authored timestamps misorder acceptance; retain verified receipt semantics, stop on contradictory evidence rather than reopen slots.
- Canonical components/API contracts touched: ledger, replay/order, `ApprovalAcceptance`, existing read-model consumers; concept correction here.
- Estimate details: stage work; replay consistency driver, receipt-order edge cases are primary uncertainty.

## Stage 3 — S3: Atomic admission and recovery
**Estimate: 0.20**

- Goal/dependencies: issue/redeem/revoke through existing signed APIs; S2.
- Expected changes: wire limit preparation/finalization; recheck capacity/authority under write lock; recover same-key acceptance without restoring separately revoked membership; allow revocation after partial use. Inspection exposes lifecycle status without recipient identities.
- Verification approach: N/N+1, concurrent last slot, simultaneous same-key claims, lost response, failed write/derived refresh and revoked approval.
- Risks: stale preparation or duplicate grant; count committed acceptance only, reconcile derived state on retry; inspect must permit authenticated recovery checks.
- Canonical components/API contracts touched: `LocalWriteService`, `IdentityApprovalAndInvitationApiController`, existing approval commit/synchronization and lock.
- Estimate details: stage work; concurrency/retry driver, failure injection uncertainty.

## Stage 4 — S4: Ordinary issuer and recipient flows
**Estimate: 0.16**

- Goal/dependencies: usable multi-recipient sharing and revocation; S3.
- Expected changes: Uses control (1 default), result limit, direct revoke for current generated invite using existing form/identifiers; retain manual revoke. Extend Lobby state/retry handling; no expiry selector/dashboard/polling.
- Verification approach: two recipients, exhausted third, same-key retry, partial revoke, QR/copy, invalid limits and failed revoke on phone/desktop.
- Risks: misleading counts or lost recovery; show signed limit, not an unrefreshed remaining count; preserve token/destination until success.
- Canonical components/API contracts touched: `invites.php`, `lobby.php`, `invite_issuance.js`, `invite_redemption.js`; reuse signing/auth/navigation and theme controls.
- Estimate details: stage work; existing-flow wiring driver, accessibility and recovery-state uncertainty.

## Stage 5 — S5: Restore and compatibility proof
**Estimate: 0.12**

- Goal/dependencies: no capacity reset or foreign authority import; S3–S4.
- Expected changes: add mixed-history repository restore/export and content-import exclusion fixtures; document upgrade/rollback/restore procedure, without broadening import policy.
- Verification approach: before/after rebuild and full restore identical recipients/counts; import neither activates excluded invites nor changes destination capacity; failed restore cannot publish fresh capacity.
- Risks: partial authority evidence; preserve existing trusted-restore rules, refuse incomplete invitation state rather than treating it as unused.
- Canonical components/API contracts touched: repository backup/restore, `ContentImportPlanner`, `ContentImportRunner`, replay; invitation reference/spec.
- Estimate details: stage work; integration evidence driver, restore-fixture completeness uncertainty.

## Stage 6 — S6: Release verification and handoff
**Estimate: 0.12**

- Goal/dependencies: prove Completion Contract and record limits; S1–S5.
- Expected changes: shared acceptance coverage, invitation-lifecycle concept entry, UI checklist, operator/reference and study closeout.
- Verification approach: focused PHP/API and actual public/members-only Invite→Lobby flows; phone/desktop across affected themes, initial/success/error/retry/revoked states, overflow/keyboard checks; run relevant regressions once.
- Risks: simulated-only confidence; inspect rendered evidence, record physical-device/production omissions; deployment stays separate.
- Canonical components/API contracts touched: existing invitation/browser suites, `docs/concepts/approval_acceptance.md`, new lifecycle entry, audit/reference/plans.
- Estimate details: stage work; cross-surface evidence driver, environmental test failures uncertainty; owns shared final verification/docs overhead.

## Execution and checkpoint

Step 2 explicitly approved; no new feature requests. Each stage targets ≤1 hour; if inspection exceeds that boundary, split before implementation without expanding scope; >8 stages/day requires rescoping. Commit each completed stage with its summary. No SQL schema change expected; any discovered schema/query-contract change follows CONTRIBUTING version/rebuild/regression requirements. Concepts: invitation replay changed; acceptance and signed review authority reused; lifecycle entry added in S6.

2026-10-31 UTC: scope v1/original baseline/R1/P2; Step 3 total **0.85 reaffirmed**, independently judged, not a sum. S1–S6 originals 0.12/0.18/0.20/0.16/0.12/0.12 are stage-work estimates only. Frozen hashes reverified; Codex single exposed rater, no measured work/accepted outcome. Planning only; links/diff checked, no runtime tests. Await Approved Step 3; planning remains uncommitted.

*— Codex · 2026-10-31 UTC*

## Mockup requested during review — 2026-10-31 UTC

Preview only: [initial page](../multi_redemption_invites_mockup/desktop-before.png), [generated five-use invite](../multi_redemption_invites_mockup/desktop-generated.png), [phone](../multi_redemption_invites_mockup/phone-generated.png). Rendered with current Light-theme components and temporary DOM substitutions; example.invalid link/QR are illustrative. App navigation omitted to show the page/dialog content. Uses defaults to one; generated example uses five; no expiry selector. Direct revoke is shown alongside the retained manual revoke disclosure. These screenshots do not implement multi-redemption or supply Step 3 approval; no runtime source changed.

*— Codex · 2026-10-31 UTC*
