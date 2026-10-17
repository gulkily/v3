> **Feature plan:** [Step 1](./root_approval_pages_step1_solution_assessment.md) · [Step 2](./root_approval_pages_step2_feature_description.md) · [Step 3](./root_approval_pages_step3_development_plan.md) · [Step 4](./root_approval_pages_step4_implementation_summary.md)

Debug mode: enabled

Status: Implementation in progress on `feature/root-approval-pages`. Planning commit: `974f320a`.

## Stage 1 - Readable seed evidence and history entry points

- Changes: Added `/approvals/root/openpgp-<fingerprint>` using the existing canonical seed loader and source-path containment checks. The themed page shows the validated key, recorded reason/body, optional current profile/account, explicit unrecorded authorizer/acceptance time, and raw-source link. All four classic/Forte account/profile history surfaces use the new route for root seeds; ordinary approval post/receipt links retain their behavior.
- Changes: Registered the route with the existing application membership boundary. Missing, malformed, path-mismatched, unreadable, or out-of-repository seed records return a safe 404. Profile lookup is optional; seed validity does not depend on a current profile. Theme asset wiring supplies wrapping key labels through existing identity styles.
- Verification: `php tests/run.php RootApprovalPageTest ApprovalHistoryTest` passed 6/6. Coverage includes all four history links, direct page/source access, malformed routes/seeds, mismatched identity, outside symlink, escaped reason/body, renamed/missing profile, private-site anonymous/forged-hint/pending-member denial, ordinary approved-member access, and unchanged ordinary post evidence. PHP syntax and `git diff --check` passed.
- Notes: No canonical/authority/schema changes. Repository provenance and final desktop/phone checks remain Stage 2. Stage 1 supports repositories without Git through current raw-source evidence.
