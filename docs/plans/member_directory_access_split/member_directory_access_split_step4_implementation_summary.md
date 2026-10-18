> **Feature plan:** [Step 1](./member_directory_access_split_step1_solution_assessment.md) · [Step 2](./member_directory_access_split_step2_feature_description.md) · [Step 3](./member_directory_access_split_step3_development_plan.md) · [Step 4](./member_directory_access_split_step4_implementation_summary.md)

Debug mode: enabled

## Scope

Standard member directory and Tools access management. Forte retains its current presentation; shared callers remain compatible. No new access powers, schema changes, or deployment.

## Stage 1 - Account history through Tools

- Changes: Tools User Access entry, account list and account history with management-specific links; public history visibility retained.
- Verification: Passed PHP lint on controller/application and `php tests/run.php UserAccessTest ApprovalHistoryTest` (2 tests). Public history/evidence and Forte history defaults checked.
- Notes: Planning commit `fc11d6ee`; todo edits excluded from commits. Later stages add review and queue before removing standard sections.

## Stage 2 - Identity review and approval

- Changes: Restricted Tools pending queue, identity-specific key review, existing shared profile access partial, and caller-specific Tools success navigation. Signing and policy APIs unchanged.
- Verification: Passed `UserAccessTest` (2), `ApprovalPolicyTest` and `ApprovalAcceptanceTest` (8), PHP lint, and `node tests/browser/approval_review.mjs` (desktop/mobile confirmation, cancellation, retry, missing review and queue approval). Queue denies anonymous/unapproved viewers; target identity and confirmed approval history verified.
- Notes: Stage 1 commit `45b9bedc`. Fixed an extracted-partial variable warning and reran focused checks without warnings.

## Stage 3 - Standard member browsing

- Changes: Standard directory and member/profile pages omit management UI; activity, messaging and concise attribution retained. Legacy pending URL redirects approved viewers to Tools and denies others; history anchors/offsets retain contextual Tools links. Legacy unsigned approval submission renders recovery in Tools.
- Verification: Passed PHP lint and `php tests/run.php UserAccessTest RootApprovalPageTest` (10). Tests cover standard separation, legacy history offset, queue redirect/access, public evidence at replacement Tools routes, and Forte filters/profile/key information.
- Notes: Stage 2 commit `3e15e4d3`. Existing root-evidence regressions now inspect replacement management routes. Stage 4 updates remaining smoke expectations for intentionally moved UI and runs serving/browser regressions.

## Stage 4 - Serving consistency and release verification

- Changes: Access routes explicitly bypass host static serving and return no-store responses; regenerated standard artifacts contain member UI only. Added page styles, retained linked attribution, and kept Forte history navigation within Forte. Updated regression expectations and UI copy checklist; todo records completed work and the unrelated baseline test failure.
- Verification: Passed new host/static and 53-key pagination regressions; queue permissions, legacy redirects/anchors, Forte pending detail API/history, private-session routes, static generation and write invalidation. Passed real HTTP browser journey at desktop/phone widths from Tools and Users, actual signed new-account/additional-key/operator approvals, cancellation/retry, no-store responses, evidence, repeated rebuild/static release rehearsal, and browser layout checks. Inspected phone screenshots for member and approval pages; no horizontal overflow in tested states.
- Verification: `php tests/run.php LocalAppSmokeTest WriteApiSmokeTest` ran 309 tests: 305 passed, three obsolete UI assertions corrected and individually rerun successfully, one unrelated feature-flag test failed. Reproduced `WriteApiSmokeTest::testPreparedFeatureFlagChangeVerifiesSignatureAndCommitsActionEvidence` unchanged on main `ed71ae0f` in an isolated worktree: missing `post_id` at line 4261. That baseline failure remains open in todo; no unrelated feature-flag changes made.
- Verification: Passed PHP lint on all changed PHP files, `git diff --check`, and planning links. Final focused regression run passed all 31 tests (`UserAccessTest`, history/policy/acceptance/root evidence, messaging, and three corrected smoke assertions).
- Notes: Stage 3 commit `0dde33b6`. Forte UI/filters/detail API retained; only shared-history link destinations stay within its existing view. Initial planning commit excludes todo edits; feature-related todo entries are included here.

## Completion

- Approval: Revised Step 3 explicitly approved; four implementation stages completed on `feat/member-directory-access-split`.
- Passed: Standard Users → member → activity/messaging rendering and Tools → User Access → exact identity → signed approval → confirmed history, including cancellation/retry, public evidence, legacy links, live authorization and static freshness checks.
- Inapplicable: Production deployment, external-service changes and schema migration are outside the approved scope. The whole repository test suite was not run; reported results are scoped regressions and the two smoke classes, with the baseline failure above.
- Request outcome: Forte exclusion implemented; no further requests pending. Public routes and existing policy/signing contracts retained.
