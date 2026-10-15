> **Feature plan:** [Step 1](./account_key_approval_safety_step1_solution_assessment.md) · [Step 2](./account_key_approval_safety_step2_feature_description.md) · [Step 3](./account_key_approval_safety_step3_development_plan.md) · [Step 4](./account_key_approval_safety_step4_implementation_summary.md)

Debug mode: enabled

Status: Implementation in progress. Stages 1–2 are implemented; stages 3–6 remain. This is not a release completion report.

Planning commit: `febc1e03` on `feature/account-key-approval-safety`.

## Stage 1 - Restrict account key approval paths

- Changes: Added the default-enabled `FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL` setting and shared approval policy. With the setting disabled, members retain new-account sponsorship and approval of their own additional keys; root-seeded operators retain cross-account authority. Direct/manual approvals and invitation redemption recheck the current policy under the write lock. Stale read models fail closed. Targets must match their canonical bootstrap and profile.
- Changes: Generic posting rejects identity protocol tags, and generic finalization rejects prepared protocol records while retaining ordinary thread/reply support. Signed flag changes require actual root-seed authority rather than approval labels. Existing grants are not re-evaluated when the setting changes.
- Changes: Fixed same-second invitation replay so issuance precedes redemption even when random record IDs sort in the opposite order. Full/incremental projections and the writer share the ordering rule; same-second revocation conservatively precedes redemption. This dependency bug was exposed by the signed acceptance/rebuild test.
- Verification: Six policy tests cover the role/account matrix, live toggles, competing first-key claims, stale metadata/markers, canonical seed mismatches, protocol endpoint bypass attempts, invitation issuer/recipient restrictions, and same-second replay ordering. A signed invitation smoke test verifies acceptance-time denial, successful retry when permitted, and grant retention across a restricted rebuild.
- Verification: The final targeted run of policy, feature flag, invitation UI, signed approval/invitation, prepared post, signed flag-change, and approval rebuild tests passed. `git diff --check` passed. The earlier broad run executed 153 tests: 147 passed and six failed. Four prepared-post regressions were corrected and passed on rerun. The vote-caption failure also reproduces against planning commit `febc1e03`; the task-queue failure has existing checkout history dating to October 9 and remains unresolved (it passes in the isolated baseline directory).
- Notes: Production was not changed. Real browser review flows, durable acceptance receipts, expiry-independent invitation history, account/operator history, and deployment/rollback verification belong to stages 2–6. Do not treat this stage as completion of the approved vertical slice.

## Resumption - October 15, 2026

- User explicitly enabled Railspike debug mode. Resumed the existing feature branch and fast-forwarded it to current main before Stage 2 work.

## Stage 2 - Review account access before signing

- Changes: Reused ApprovalPolicy context for the three action-specific labels, approved-key count warning, and exact target-key confirmation. Profile controls show the consequence; pending controls open a review. Browser signing requires a complete review and cancellation performs no signing or finalization. Invitation issuance explains bearer-link scope and acceptance-time policy enforcement.
- Changes: Prepared approvals include a signed context digest and retain the complete context in server-owned preparation metadata. Finalization compares live context under the existing write lock and rejects changed accounts, counts, policy, or authority, requiring refresh/review/retry. Existing canonical-byte and signature checks prevent target substitution; expired preparations remain rejected.
- Verification: `php tests/run.php ApprovalPolicyTest BrowserSigningNormalizationTest` passed 87 tests. The final policy rerun passed six tests, including changed-count and changed-policy review invalidation, all three labels, target substitution, and expiry. Five focused WriteApiSmokeTest tests passed for profile controls, preparation, signed finalization, and invitation acceptance. `node tests/browser/approval_review.mjs` passed desktop/mobile confirmation, cancellation without signing, retry, and missing-review rejection. Browser review uses isolated transport/signing doubles; real signed acceptance is covered by the PHP smoke tests. `git diff --check` passed.
- Notes: Debug mode was explicitly enabled on resumption. No production changes. Durable acceptance evidence and history remain stages 3–6; the signed review digest alone is not acceptance evidence.
