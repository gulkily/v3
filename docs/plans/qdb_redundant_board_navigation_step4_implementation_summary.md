# QDB Redundant Board Navigation — Step 4: Implementation Summary

> **Feature plan:** [Step 1](./qdb_redundant_board_navigation_step1_solution_assessment.md) · [Step 2](./qdb_redundant_board_navigation_step2_feature_description.md) · [Step 3](./qdb_redundant_board_navigation_step3_development_plan.md) · [Step 4](./qdb_redundant_board_navigation_step4_implementation_summary.md)

## Stage 1 - Omit generic controls for QDB listings

- Changes:
  - Reused the existing `isQdbInstance` board-template input to omit the
    generic `board-controls-nav` card only for QDB listing renders.
  - Retained the controls card unchanged for non-QDB boards.
- Verification:
  - `php -l templates/pages/board.php` — passed.
  - `php tests/run.php QuoteCardDisplayNumberTest QdbExperienceRoutingTest` —
    24 passed.
  - `git diff --check` — passed.
- Notes:
  - QDB header navigation, routes, quote ordering, pagination, reactions, and
    footer were not changed. Deployment verification is pending Stage 2's
    focused markup assertions.

## Stage 2 - Lock in QDB-only navigation coverage

- Changes:
  - Added a route-rendering regression test for QDB Latest, Top, and 1337;
    each asserts the generic controls markup is absent and the matching header
    link is active.
  - Added the paired default-profile assertion that generic board controls
    remain present outside QDB.
- Verification:
  - `php -l templates/pages/board.php` and
    `php -l tests/QuoteCardDisplayNumberTest.php` — passed.
  - `php tests/run.php QuoteCardDisplayNumberTest QdbExperienceRoutingTest PresentationProfileMatrixTest` — 27 passed.
  - `php tests/run.php` — 881 passed; 2 long-standing, unrelated failures:
    `WriteApiSmokeTest::testQdbPermalinkShowsViewersExistingUpvoteAsPressedAndDisabled`
    (five consecutive failures since 2026-10-09) and
    `WriteApiSmokeTest::testTaskQueueProcessesQueuedAgentReplyOnce` (ten
    consecutive failures since 2026-10-08).
  - `git diff --check` — passed.
- Notes:
  - The test asserts the controls element's exact class rather than the raw
    `board-controls-nav` token, which legitimately remains in bundled CSS.
  - Deployment verification remains the approved post-deploy route inspection;
    no deployment configuration changed in this feature.
