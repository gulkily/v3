> **Feature plan:** [Step 1](./multi_redemption_invites_step1_solution_assessment.md) · [Step 2](./multi_redemption_invites_step2_feature_description.md) · [Step 3](./multi_redemption_invites_step3_development_plan.md) · Step 4
Debug mode: enabled
Effort study: railspike-effort / v3/multi_redemption_invites; [ledger](../effort_estimation_trial.md)
Extended estimates: enabled
**Cycle estimate: 0.70**

Authorization: User said “approved step 3” on 2026-10-31. Backend-only v2; six stages, no frontend changes, dependencies, merge, push or deployment.
Branch: feat/multi-redemption-invites
Planning commit: 465d3b38
Step 4 entry: 2026-10-31, Codex single exposed rater; 0.70 reaffirmed, original baseline/R1/P2 retained. All three frozen R1 hashes verified. No measured labor or maintainer acceptance.
Stage originals: S1 0.12; S2 0.18; S3 0.20; S4/v2 0.08; S5 0.12; S6/v2 0.08. Scope and uncertainty inherited from approved Step 3; non-additive. V1 history preserved separately.
Reuse: Extend src/ForumRewrite/Canonical/InvitationRecord.php and InvitationRecordParser.php; GenericTextRecordParser already rejects duplicate headers. Extend Invitation/InvitationLedger.php and InvitationApprovalReplay.php; preserve Security/ApprovalAcceptance.php receipt authority. Extend Write/LocalWriteService.php and existing Http/IdentityApprovalAndInvitationApiController.php contracts. Existing tests and browser signing flows supply fixtures; no new endpoint or frontend component.
Completion: Signed APIs admit N distinct keys, refuse N+1, recover same-key retry and partial revocation, preserve replay/restore counts and unchanged browser entry.

*— Codex · 2026-10-31 UTC*

## Stage 1 - S1: Signed issuance contract

**Original estimate: 0.12**
**Retrospective stage-work assessment: 0.12**

- Changes: Optional bounded issuance header in canonical parser/model; absent remains 1. Existing generic parser rejects duplicate headers; exact prepared-byte/signature checks will remain the API authority. Added format and upgrade boundary specification.
- Verification: php tests/run.php CanonicalRecordParsersTest — 53/53 passed; valid endpoints, malformed/overflow/duplicate limits and non-issuance rejection covered. Runtime issuance deliberately awaits S3 after replay validation; signature tampering checked there.
- Notes: No dependencies, schema or frontend edits. Original unchanged; retrospective is judgment, not measured labor.

*— Codex · 2026-10-31 UTC*
