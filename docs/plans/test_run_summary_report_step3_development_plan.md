# Step 3: Development Plan — Test Run Summary Report

## Stage 1
- Goal: Add a local SQLite-backed history store for per-test run results.
- Dependencies: None.
- Expected changes:
  - New `tests/Support/TestRunHistoryStore.php` class (namespaced or plain, matching `tests/` conventions).
  - Conceptual schema: single table `test_run_results` keyed by `test_name`, columns for `last_status`, `consecutive_fail_count`, `first_failed_at`, `last_changed_at`, `last_run_at`.
  - Constructor: `__construct(string $databasePath)`; ensures parent directory exists and opens PDO sqlite connection (mirrors `ReadModelConnection::open()` style).
  - `ensureSchema(): void` — creates table if missing.
  - `recordResults(array $results, string $runAt): array` — `$results` is `[testName => bool $passed]`; upserts each row, returns `[testName => ['classification' => 'new_failure'|'long_standing_failure'|'recovered'|'still_passing'|'still_failing', 'consecutiveFailCount' => int, 'firstFailedAt' => ?string]]`.
- Verification approach: Unit test (`tests/TestRunHistoryStoreTest.php`) covering: first-ever failure classifies as `new_failure`; repeated failure increments streak and classifies `long_standing_failure`; failure-then-pass classifies `recovered`; pass-then-pass classifies `still_passing`.
- Risks or open questions:
  - Default DB path and directory creation if `state/` is absent on a fresh checkout.
- Canonical components/API contracts touched: New store only; no existing SQLite helper (`ReadModelConnection`) is reused since it's app-specific, but its connection-opening style is followed for consistency.

## Stage 2
- Goal: Wire result capture into the existing test loop in `tests/run.php`.
- Dependencies: Stage 1 (`TestRunHistoryStore`).
- Expected changes:
  - Add `$testResults[$testName] = bool` alongside the existing `try/catch` in the run loop (currently only builds `$failures` strings and `$testDurations`).
  - Determine DB path via `FORUM_TEST_HISTORY_DB_PATH` env var, default `state/test_run_history.sqlite`.
  - After the run loop (before existing pass/fail exit branches), call `$store->ensureSchema()` then `$store->recordResults($testResults, date('c'))`, capturing the returned classifications for Stage 3.
- Verification approach: Run `php tests/run.php SomeTestClass` twice; confirm `state/test_run_history.sqlite` is created/updated and no PHP errors occur if `state/` doesn't pre-exist.
- Risks or open questions: None.
- Canonical components/API contracts touched: `tests/run.php` (the sole test-output surface).

## Stage 3
- Goal: Build and print the summary block (counts, failures, slow tests, history).
- Dependencies: Stage 2 (classifications available).
- Expected changes:
  - New function `printRunSummary(int $runCount, array $failures, array $testDurations, array $classifications, mixed $stream): void` in `tests/run.php`.
  - Prints: total run / passed / failed counts; failing tests with error messages (from existing `$failures`); slow tests (delegates to existing `printSlowTestsOverThreshold`); history section listing new failures, long-standing failures (with streak count + first-failed date), and recovered tests (derived from `$classifications`).
  - Replace the current ad-hoc "All tests passed." / failure-exit output with a single call to `printRunSummary`, keeping exit code logic (`exit(1)` on failures, `exit(0)` otherwise) unchanged.
- Verification approach: Run full suite once (baseline), intentionally break one test and rerun (expect "new failure"), rerun again unchanged (expect "long-standing failure, 2 runs"), fix it and rerun (expect "recovered"). Confirm counts match number of PASS/FAIL lines.
- Risks or open questions: None.
- Canonical components/API contracts touched: `tests/run.php` summary/reporting output (extends existing `printSlowTestsOverThreshold` usage, does not fork it).

## Stage 4
- Goal: Confirm filtered runs (single class/method) produce a correctly scoped summary and the history DB stays untracked.
- Dependencies: Stage 3.
- Expected changes: None expected (behavioral verification only); minor fix-up if filtered runs mis-scope counts or history lookups.
- Verification approach:
  - Run `php tests/run.php SomeTestClass::testMethod`; confirm summary counts/history only reflect the filtered test(s), not the full suite.
  - Run `git status`; confirm `state/test_run_history.sqlite` does not appear (already covered by existing blanket `state/` gitignore entry — verify, don't add a new rule).
- Risks or open questions: None.
- Canonical components/API contracts touched: `tests/run.php` filtering path (`shouldRunClass`/`shouldRunMethod`), unchanged logic, verification only.
