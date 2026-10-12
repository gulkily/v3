> **Feature plan:** [Step 1](./case_insensitive_tags_step1_solution_assessment.md) · [Step 2](./case_insensitive_tags_step2_feature_description.md) · [Step 3](./case_insensitive_tags_step3_development_plan.md) · [Step 4](./case_insensitive_tags_step4_implementation_summary.md)

Debug mode: enabled

## Implementation status

Feature branch: `feature/case-insensitive-tags`. Planning commit: `da3af89d`.

## Stage 1 - Online tag browsing

- Changes: merge ASCII case variants without changing stored content; normalize headings/links; recognize uppercase tag segments in dispatch and private-site route classification.
- Verification: `php tests/run.php LocalAppSmokeTest::testTagBrowsingCombinesCaseVariantsWithoutDuplicateThreads LocalAppSmokeTest::testApprovedPrivateSessionCanViewOwnProfileAndBoard LocalAppSmokeTest::testPrivateForteRoutesRecoverExpiredSessionsInsteadOfReturningFalseNotFound PrivateSiteAuthTest` — 12 passed. Fixtures cover six unique threads, overlapping label/tag variants, five-item previews, hidden identity content, six URL forms, unknown tags, and unchanged source records. `git diff --check` passed.
- Notes: access regression assertions belong in LocalAppSmokeTest, which exercises PHP routing; PrivateSiteAuthTest covers browser authentication and was run unchanged. The broader `LocalAppSmokeTest::testApplicationRendersCoreRoutes` fails on an unrelated account-key toolbar assertion, reproduced at planning baseline `da3af89d` in `/tmp/case-tags-baseline`. No schema or migration change.
