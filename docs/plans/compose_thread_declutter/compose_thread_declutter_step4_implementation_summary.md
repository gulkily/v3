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

## Stage 2 - Tags/subject expando
- Changes:
  - `templates/partials/thread_compose_form.php`: non-compact form now renders the body first, then a `<details data-compose-disclosure="tags-subject">` wrapping board tags and subject with unchanged names and status hooks. Opens server-side when tags ≠ `general`, subject is non-empty, or an error is present; summary shows current subject and tags.
  - Body normalization-status paragraph now sits directly under the body in both variants.
  - `public/assets/compose.css`: shared `.compose-disclosure` styling.
  - `tests/LocalAppSmokeTest.php`: closed-by-default and open-on-subject/open-on-tags assertions.
- Verification:
  - `php tests/run.php LocalAppSmokeTest PresentationProfileMatrixTest LazyComposeSigningTest OfflineOutboxComposeTest BrowserSigningNormalizationTest`: 225 run, 225 passed.
- Notes:
  - Query-string and quote-supplied values are covered by the same non-default rule; the error open path is not yet covered by a test and the normalization-warning open path arrives in Stage 4.
  - Event inputs are still visible; Stage 3 wraps them.
