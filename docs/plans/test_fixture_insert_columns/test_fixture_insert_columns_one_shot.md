# Test fixture insert columns

Cycle v3/test_fixture_insert_columns · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/test_fixture_insert_columns`; host ledger [test_fixture_insert_columns.md](../effort_ledger/test_fixture_insert_columns.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.10**

Status: implemented, PR open; next permitted action: maintainer merge.

## Original request

"let's fix all these as three one-shots?" (Ilya, 2026-11-22), about the follow-ups left by [named_column_inserts](../named_column_inserts/named_column_inserts_one_shot.md). This is the second: inserts in test fixtures that rely on column order.

## Authorization

A bounded one-shot through an open PR. Tests only. Not authorized: a merge, or any change under `src/`.

## Outcome and work type

Test change. Every insert under `tests/` names its columns, and `SqlInsertColumnsTest` scans `tests/` as well, so a fixture can't bring the pattern back.

## Why

A fixture insert without a column list fails when its table gains a column, even though the code under test is fine. That costs a debugging round on an unrelated change.

## Audit (baseline `cc6680c9`)

46 inserts in 23 files under `tests/` (the earlier count of 49 included three sample strings inside `SqlInsertColumnsTest`). Most fill a small table the test creates itself; a few fill real tables (`viewer_sessions`, `visitor_statistics_daily`, `metadata`, `username_routes`).

One is a row copy, `INSERT INTO profiles SELECT * FROM member` in `EventStatusTest`. It becomes an insert that names every column on both sides, using the column list the test already reads from `PRAGMA table_info`.

## Risks

- **A wrong column list changes what a fixture stores.** Mitigate by taking each list from the `CREATE TABLE` the insert targets, checking that the column count equals the value count, and running every changed test.
- **Two support files run only in browser tests** (`tests/Support/fastmod_browser_worker.php`, `private_message_browser_fixture.php`). Their lists are checked by reading against the table definition, not by a run.

## Reuse decisions

Extend the existing guard's directory list. No new test, tooling or dependency.

## Completion conditions

Smoke set plus all changed test classes pass. PR open. Feedback: no matching item in `docs/feedback/items/`. Concepts touched: none.

Starting revision and branch: `cc6680c9`, `fix/test-fixture-insert-columns`. Planning commit: `c178fba6`.

## Study history

- 2026-11-22 UTC, intake and entry: **0.10**. Claude, single exposed rater. R1 not reverified this session.
- 2026-11-22 UTC, closeout: retrospective **0.10**. Same rater.

## Outcome

All 46 fixture inserts name their columns. `SqlInsertColumnsTest` scans `tests/` (skipping its own sample strings), and `CONTRIBUTING.md` says the rule covers fixtures. Nothing under `src/` changed.

## Verification

- Passed: `./v3 test --smoke` (484 run, 0 failed).
- Passed: the 23 changed test classes (119 run, 0 failed).
- Passed: a probe file under `tests/Support/` with `INSERT INTO foo VALUES (1)` made the guard fail; the probe was removed.
- Not run: the full suite, and the browser tests that use the two support files.

## Final review

Diff: 23 test files, the guard, one sentence in `CONTRIBUTING.md`, this record, the index, the ledger and the debug log. Merges cleanly with the open `fix/read-model-insert-columns` PR.

Commit, merge and publication: pushed to `fix/test-fixture-insert-columns` with an open PR; not merged.

— Claude · 2026-11-22 UTC · Model: claude-fable-5-2
