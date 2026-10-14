> **Feature plan:** [Step 1](./test_runner_elapsed_time_step1_solution_assessment.md) · [Step 2](./test_runner_elapsed_time_step2_feature_description.md) · [Step 3](./test_runner_elapsed_time_step3_development_plan.md) · [Step 4](./test_runner_elapsed_time_step4_implementation_summary.md)

Debug mode: disabled

## Problem

Test results currently show no timing until the later slow-test report, making it difficult to follow overall progress or identify the cost of each completed test.

## User stories

- As a developer, I want total run time beside each result so that I can track progress while the suite runs.
- As a developer, I want each test's duration at the end of its result line so that I can spot slow tests immediately.

## Core requirements

- Every completed PASS/FAIL result begins with bracketed elapsed run time and ends with bracketed individual test duration. Both use seconds with two decimal places and an `s` suffix; neither includes the words “elapsed” or “duration.”
- Run time starts when the PHP runner begins, includes loading/setup and time between tests, and never moves backward. Individual duration measures only that test's execution through completion or caught failure; fast tests may round to zero.
- Print the result after completion, by default in full and filtered runs, identically in terminals and redirected logs.
- Preserve test identity, failure diagnostics, output streams, exit statuses, filtering, history, and existing summary/slow-test/interruption reports. For multiline failures, put duration on the initial FAIL line and retain subsequent diagnostic lines.
- Leave summary lines and output emitted directly by tests untimed; an interrupted unfinished test must not receive a fabricated completed result.

## Delivery scope and completion boundary

Work type: application change to developer tooling. Extend the existing runner, its behavior tests, and test-output documentation. Normal entry is `./v3 test` or direct PHP runner invocation; completion is readable timing on every finished result, with failure and interruption behavior preserved and applicable regression checks passing. No live timer, completion estimate, new timing history, standalone-runner changes, or application feature changes.

## Risks

- **Output compatibility:** Prefixes can break consumers expecting PASS/FAIL at column one. Validate repository consumers before implementation; update affected checks and document the new format while preserving status text and streams.
- **Misleading timing:** Confusing run time with test duration defeats the goal. Validate startup, sequential tests, fast results, and failures early; enforce fixed positions and stable elapsed measurements.
- **Diagnostic regressions:** Failure formatting or interruption could lose details or duplicate results. Validate multiline failure and interrupted-run cases early; retain existing diagnostics and completion semantics.

## Shared component inventory

- Existing PHP runner: extend its canonical PASS/FAIL output and reuse its per-test timing; preserve its summary, failure recap, slow-test, and interrupted-run surfaces.
- CLI test entry points: reuse the same runner; no separate formatter or UI/API surface is needed.
- Existing runner behavior tests and README test instructions: extend coverage and explain the two timing positions.

## Simple user flow

1. Start a full or filtered test run.
2. Read total run time on the left and the finished test's duration on the right.
3. Review failures and the usual summary, or interrupt with the existing partial report.

## Success criteria

All completed results have both timings in the specified positions; sequential elapsed values are nondecreasing. Passing, failing, fast, filtered, redirected, multiline-failure, and interruption checks pass, with no unrelated suite regressions.
