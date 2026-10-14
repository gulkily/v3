> **Feature plan:** [Step 1](./test_runner_elapsed_time_step1_solution_assessment.md) · [Step 2](./test_runner_elapsed_time_step2_feature_description.md) · [Step 3](./test_runner_elapsed_time_step3_development_plan.md) · [Step 4](./test_runner_elapsed_time_step4_implementation_summary.md)

Debug mode: disabled

## Completion Contract

- Normal entry: full or filtered runs through `./v3 test` and direct PHP runner invocation.
- Outcome: each completed PASS/FAIL line starts with total elapsed run seconds and ends with individual test seconds, both bracketed with two decimal places and an `s` suffix, without timing labels.
- Recovery: retain failure details, streams, exit statuses, summaries, history, and interruption reporting; never report an interrupted unfinished test as completed. Multiline failure continuation lines remain intact and untimed.
- Release condition: focused runner checks, full suite, PHP syntax, documentation, and whitespace checks pass. CLI tooling needs no deployment or external-service verification; unrelated standalone suites need no changes.
- Exclusions: live timers, completion estimates, stored timing history, application features, and standalone-runner output.

## Key Risks

- Output consumers may assume PASS/FAIL starts the line. Search repository consumers before edits; adapt affected checks and document the intentional prefix change before verification.
- Incorrect timing boundaries or wall-clock changes could mislead readers. Validate startup inclusion and sequential results early; use monotonic elapsed measurements and one completion measurement per test.
- Failure or interruption handling could lose diagnostics or duplicate results. Exercise these paths in subprocess checks before the full suite; preserve existing exception and signal behavior.

## Stage 1

- Goal: deliver the complete timed-result experience with regression coverage and usage documentation; estimated effort under one hour.
- Dependencies: approved Steps 1–3; create a feature branch, organize the approved plans under `docs/plans/test_runner_elapsed_time/`, repair navigation/index links, and commit planning artifacts before implementation.
- Expected changes: extend `tests/run.php` with run-start timing and consistent completed-result formatting, using the existing per-test measurements for inline duration and slow-test reporting. Keep timing outside stored failure messages. Extend `tests/TestRunnerBehaviorTest.php` and small isolated fixtures if needed; update the README test instructions. No database or public API changes.
- Verification approach: assert numeric formatting and positions for passing, fast, and failing tests; preserve multiline diagnostic content and stdout/stderr separation. Check full versus filtered entry points, redirected output, nondecreasing run time, startup inclusion, and per-test timing boundaries without brittle exact-duration assertions. Reuse interruption checks and assert no completed result for the interrupted fixture. Run focused tests first, then `./v3 test`, PHP syntax checks, and `git diff --check`; inspect a terminal run as well as captured logs.
- Risks or open questions: output compatibility and asynchronous interruption are the main risks; resolve consumer assumptions and fixture synchronization before accepting results. Keep fixture state/history isolated from normal runs. No unresolved product decisions remain.
- Canonical components/API contracts touched: the single PHP runner and its existing CLI delegates, result streams, slow-test/summary/interruption reporters, runner behavior tests, and README. Reuse these components without a second runner or formatter entry point.
- Completion checkpoint: record changes, verification results, and limitations in Step 4; commit the implementation, tests, documentation, and summary together after checks pass.
