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
