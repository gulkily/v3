> **Feature plan:** [Step 1](./instance_content_sync_step1_solution_assessment.md) · [Step 2](./instance_content_sync_step2_feature_description.md) · [Step 3](./instance_content_sync_step3_development_plan.md) · [Step 4](./instance_content_sync_step4_implementation_summary.md)

## Stage 1 - Public content coverage
- Changes: Added shared canonical archive catalog and public-content planner, including subject changes, semantic authority exclusions, dependency/signature grouping, duplicate/conflict detection, and byte hashes. Local archive CLI reuses the catalog and retains its options. Moved the four planning artifacts into their feature folder and updated navigation.
- Verification: `php tests/run.php ContentImportPlannerTest RepositoryArchiveImportCommandTest` — 4/4 passed; new PHP classes linted; `git diff --check` passed.
- Notes: Legacy posts without explicit timestamps are reported as invalid instead of rewriting signed content or importing foreign Git history. Source signatures are preserved, not represented as newly authenticated submissions.
