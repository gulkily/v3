# Step 4: Implementation Summary — Test Run Summary Report

## Stage 1 - SQLite history store
- Changes:
  - Added `tests/Support/TestRunHistoryStore.php`: SQLite-backed store (`test_run_results` table) with `ensureSchema()` and `recordResults(array $results, string $runAt): array`, classifying each test as `new_failure`, `long_standing_failure`, `recovered`, or `still_passing` and tracking consecutive-fail streaks + first-failed timestamp.
  - Added `tests/TestRunHistoryStoreTest.php` covering all four classification paths.
  - Registered `TestRunHistoryStoreTest.php` in `tests/run.php`'s `$testFiles` list.
- Verification:
  - `php tests/run.php TestRunHistoryStoreTest` — all 4 tests pass.
- Notes:
  - Store creates its parent directory on first write, so it works even if `state/` doesn't exist on a fresh checkout.
  - Not yet wired into the main run loop or gitignored path — that's Stage 2.

## Stage 2 - Wire result capture into the run loop
- Changes:
  - `tests/run.php`: require `Support/TestRunHistoryStore.php`; track `$testResults[$testName] = bool` in the existing try/catch; after the run loop (before the pass/fail exit branches), resolve DB path from `FORUM_TEST_HISTORY_DB_PATH` env var (default `state/test_run_history.sqlite`), call `ensureSchema()` and `recordResults($testResults, date('c'))`, storing classifications in `$testClassifications` for Stage 3.
- Verification:
  - `rm -f state/test_run_history.sqlite && php tests/run.php TestRunHistoryStoreTest ThemeRegistryTest` — all 10 tests pass, exit code unchanged.
  - Inspected `state/test_run_history.sqlite` via `sqlite3` — one row per executed test with `last_status = pass`.
- Notes:
  - `state/` is already blanket-gitignored, so no new ignore rule needed (confirmed in Stage 4).
  - `$testClassifications` is computed but not yet printed — that's Stage 3.
