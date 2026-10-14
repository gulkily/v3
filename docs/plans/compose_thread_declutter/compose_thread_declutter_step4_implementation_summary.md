> **Feature plan:** [Step 1](./compose_thread_declutter_step1_solution_assessment.md) · [Step 2](./compose_thread_declutter_step2_feature_description.md) · [Step 3](./compose_thread_declutter_step3_development_plan.md) · [Step 4](./compose_thread_declutter_step4_implementation_summary.md)

Debug mode: enabled

## Stage 1 - Trim the page header
- Changes:
  - `templates/pages/compose_thread.php`: heading "Compose Thread" → "New thread"; replaced the "Posts are stored as canonical text files…" paragraph with one short line.
  - `tests/Support/ProfileRegressionContract.php`: compose marker updated to the new heading.
- Verification:
  - `php tests/run.php LocalAppSmokeTest PresentationProfileMatrixTest`: 140 run, 140 passed.
- Notes:
  - The document `<title>` still reads "Compose Thread" (from `QdbExperience`), which serves as the accessible page title, so no hidden heading was added.
