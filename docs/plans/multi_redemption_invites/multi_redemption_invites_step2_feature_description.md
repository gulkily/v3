> **Feature plan:** [Step 1](./multi_redemption_invites_step1_solution_assessment.md) · [Step 2](./multi_redemption_invites_step2_feature_description.md) · [Step 3](./multi_redemption_invites_step3_development_plan.md) · [Step 4](./multi_redemption_invites_step4_implementation_summary.md)
Debug mode: enabled
Effort study: railspike-effort / v3/multi_redemption_invites; [P2 ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
**Cycle estimate: 0.70**

## Problem and user stories

Expose capped multi-redemption through existing signed invitation APIs. User revision: “let us not update the ui at all, we'll make this a backend-only feature”.

- As an API client, I want a signed use limit so one link admits a bounded number of distinct recipient keys.
- As a recipient client, I want idempotent recovery so retries do not consume another use.
- As an issuer, I want API revocation after partial use so unused capacity closes without removing earlier approvals.

## Core requirements

- Extend API issuance with an optional positive whole-number limit; omitted means one. Preserve the existing single-use contract. Browser-issued invites remain single-use with the shipped 30-day default; API custom expiry stays supported. Count keys, not people.
- Count successfully accepted distinct recipients only. Inspection/preparation, failures, existing-member entry and duplicate retries consume no capacity; final-slot races cannot exceed the signed cap.
- Revocation and expiry prevent new admissions; previously accepted grants remain. Same-key recovery does not create another grant or restore separately revoked membership. Preserve existing response/error contracts for unchanged browser clients.
- Legacy records/signatures remain unchanged. Full/incremental replay and trusted full restore preserve accepted recipients and capacity; content import continues excluding invitation authority. Document older-reader upgrade/rollback boundaries.
- No template, CSS, browser-script, UI copy, control, dialog, navigation or visual-layout changes. Existing recipient entry/retry flows remain compatible with API-created links. Any frontend change found necessary is outside scope and requires a new decision, not silent expansion.

## Delivery scope and shared components

Work type: backend application change; API/spec/reference documentation, backend/compatibility tests, concept and study records. Exclude frontend implementation, mockup implementation, new CLI, dashboard, unlimited mode, dependencies and deployment.

Extend `InvitationRecord`/parser, `InvitationLedger`, `InvitationApprovalReplay`, `LocalWriteService` and `IdentityApprovalAndInvitationApiController`; reuse approval receipts, locks and read-model consumers. No parallel service/endpoints. Invite, Lobby, Account, source/history and Users remain existing consumers; `invites.php`, `lobby.php`, `invite_issuance.js`, `invite_redemption.js` and shared signing/auth/navigation are compatibility targets, not edit targets.

Concepts: [invitation replay](../../concepts/approval_acceptance.md#invitation-approval-replay) changes; acceptance/signed-review authority reused. Add backend invitation-lifecycle inventory during implementation.

## Risks and flow

- Over-admission: prove locked final acceptance and same-key retry behavior before enabling extended API issuance.
- Replay/restore loss: validate receipt ordering, mixed histories and capacity parity; never reinterpret incomplete evidence as unused capacity.
- Client/older-reader mismatch: document upgrade and API key-count semantics; verify unmodified browser consumers. A required UI change blocks that approach until explicitly rescoped.

1. Authenticated issuer prepares/signs/finalizes a capped invite through the existing API.
2. Distinct eligible recipients redeem through signed APIs or the unchanged Lobby; retry resumes accepted access.
3. Issuer signs revocation through the existing API; remaining admission closes.

Completion boundary: executable API examples demonstrate N admissions, N+1 refusal, duplicate/lost-response recovery and partial revocation; race, replay, trusted-restore and content-import exclusion tests pass. Existing public/members-only browser issuance and API-created-link redemption remain compatible. No visual redesign or theme matrix is needed absent presentation changes; report actual browser and production limitations.

## Approval and study checkpoint

Scope v2: backend-only, expressly directed by user on 2026-10-31 UTC; earlier Step 1/2 approvals and v1 requirements/stages are preserved in [scope history](multi_redemption_invites_scope_v1_history.md). This revises the approved requirements, not implementation authority. No UI change is authorized. Await revised Step 3 approval.

R1/P2/original baseline retained; Codex single exposed rater; frozen hashes reverified. Earlier whole-cycle projections 0.85 retained; **v2 total 0.70**, reduced by removing frontend implementation and visual acceptance. No measured work or accepted outcome. Debug/extended enabled; documentation only, new records uncommitted.

*— Revised by Codex · 2026-10-31 UTC*

Approval confirmation — 2026-10-31 UTC: user explicitly approved Steps 1 and 2 again after the backend-only revision. Scope v2 is approved for development planning; Step 3/implementation remains pending.

*— Codex · 2026-10-31 UTC*
