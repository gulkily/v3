# QDB Redundant Board Navigation — Step 3: Development Plan

> **Feature plan:** [Step 1](./qdb_redundant_board_navigation_step1_solution_assessment.md) · [Step 2](./qdb_redundant_board_navigation_step2_feature_description.md) · [Step 3](./qdb_redundant_board_navigation_step3_development_plan.md) · [Step 4](./qdb_redundant_board_navigation_step4_implementation_summary.md)

## Completion Contract

- Normal entry: a visitor opens QDB `/latest`, `/top`, or `/leetness`.
- End-to-end outcome: the selected quote list retains header navigation,
  quote content, pagination, reactions, and footer without generic board
  controls.
- Required recovery: a non-QDB board retains its generic controls; QDB header
  links remain usable if a visitor needs another supported listing.
- Deployment/external verification: inspect the three QDB listing routes and a
  non-QDB board in the rendered application after deployment.
- Release condition: focused render coverage and relevant automated tests pass
  with no change to routing or quote-list behavior.

## Key Risks

- Impact: suppressing controls globally would remove standard forum browsing.
  Early validation: render a non-QDB board. Mitigation: derive the template
  choice from the existing QDB policy passed to the board controller.
- Impact: CSS-only hiding would leave redundant accessibility navigation and
  fail under other allowed themes. Early validation: assert markup absence.
  Mitigation: conditionally omit the controls from the server-rendered page.
- Impact: rendering changes could accidentally affect listing content or
  pagination. Early validation: exercise Latest, Top, and 1337 routes.
  Mitigation: alter presentation only; retain current data and route paths.

## Stage 1

- Goal: remove the generic controls only from QDB quote listings.
- Dependencies: approved Steps 1–3 planning artifacts.
- Expected changes: have the board-rendering path expose whether generic board
  controls should render, based on the existing QDB policy; make the shared
  board template conditionally render its controls card. Leave QDB header
  navigation, board options, routes, and all non-QDB behavior unchanged.
- Verification approach: PHP syntax-check changed source/template files and
  render QDB Latest, Top, and 1337 plus a non-QDB board for a markup check.
- Risks or open questions:
  - Impact: the shared template could hide controls for other profiles.
  - Early warning / validation: non-QDB render lacks `board-controls-nav`.
  - Mitigation: default the presentation choice to retain controls unless the
    existing QDB policy is present.
- Canonical components/API contracts touched: `BoardPageController::board()`
  presentation data and `templates/pages/board.php`; reuse
  `QdbPresentation::navigation()` unchanged.

## Stage 2

- Goal: lock in the QDB-only navigation contract with focused regression
  coverage and record final verification.
- Dependencies: Stage 1 completed and its presentation behavior rendered.
- Expected changes: add or extend route-rendering coverage to assert that
  `/latest`, `/top`, and `/leetness` omit `board-controls-nav` while a
  non-QDB board retains it; add the Stage 4 implementation summary.
- Verification approach: PHP syntax-check changed tests, run the focused QDB
  render tests and relevant presentation-profile coverage, then run
  `git diff --check` and the appropriate test suite.
- Risks or open questions:
  - Impact: tests could prove only one listing route and miss a route-specific
    regression.
  - Early warning / validation: every supported QDB listing route is asserted.
  - Mitigation: exercise all three routes and one non-QDB control case.
- Canonical components/API contracts touched: existing QDB route/render test
  surfaces, `templates/pages/board.php`, and `BoardPageController::board()`.

Waiting for "Approved Step 3" before Step 4 implementation.
