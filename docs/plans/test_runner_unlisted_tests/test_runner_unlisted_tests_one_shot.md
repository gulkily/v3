# Test runner: unlisted and unmatched tests

Cycle v3/test_runner_unlisted_tests · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/test_runner_unlisted_tests`; host ledger [test_runner_unlisted_tests.md](../effort_ledger/test_runner_unlisted_tests.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.10**

Status: plan written; next permitted action: implement.

## Original request

"let's fix all these as three one-shots?" (Ilya, 2026-11-22), about the follow-ups left by [named_column_inserts](../named_column_inserts/named_column_inserts_one_shot.md). This is the third: a new test file can be silently skipped.

## Authorization

A bounded one-shot through an open PR, limited to the test runner, its test and its docs. Not authorized: a merge.

## Problem

Two gaps in `tests/run.php` combine to hide a test that never ran:

1. The runner loads only the files named in its `$testFiles` list. A new `tests/FooTest.php` that isn't added there is never loaded.
2. `./v3 test --smoke FooTest` then reports success. The runner fails on unmatched names only when nothing ran at all, and the smoke set always runs.

This happened in `named_column_inserts`: the new guard test "passed" twice before it had ever run.

## Outcome and work type

Tooling change.

1. The runner also loads every `tests/*Test.php` that the list doesn't name, after the listed files, in name order. A new test file needs no entry. The list stays, because it fixes the order of the existing files.
2. Each test named on the command line must exist, as a class or a `Class::method`. If one doesn't, the runner prints `Test not found: <name>` and exits 1 before running anything. Smoke list entries already work this way.

## Risks

- **A file named `*Test.php` that isn't a test gets loaded.** Checked: every such file in `tests/` is already in the list, so nothing new loads today.
- **Parallel workers receive names from the coordinator.** They pass through the same check, which they satisfy by construction. Validate with the existing parallel runner test.

## Reuse decisions

- The smoke-entry check (`Smoke list entry not found`) already has the right shape; the new check shares one helper with it.
- Tests go in the existing `TestRunnerBehaviorTest` with its `test_runner_timing.php` fixture.
- Docs: the Tests section of `CONTRIBUTING.md`.

## Completion conditions

Smoke set plus `TestRunnerBehaviorTest` pass. A name that matches nothing fails with and without `--smoke`. PR open. Feedback: no matching item in `docs/feedback/items/`. Concepts touched: none.

Starting revision and branch: `cc6680c9`, `fix/test-runner-unlisted-tests`.

## Study history

- 2026-11-22 UTC, intake and entry: **0.10**. Claude, single exposed rater. R1 not reverified this session.

— Claude · 2026-11-22 UTC · Model: claude-fable-5-2
