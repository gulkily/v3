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

## Stage 3 - Build and print the summary block
- Changes:
  - `tests/run.php`: added `printRunSummary(int $runCount, array $failures, array $testDurations, array $classifications, mixed $stream): void`, printing counts, failing tests with error messages, slow tests (delegates to existing `printSlowTestsOverThreshold`), and history sections (new failures, long-standing failures with streak + first-failed date, newly recovered).
  - Replaced the old "All tests passed." / early-exit failure block with a single `printRunSummary(...)` call before the `exit(1)` check; exit code logic unchanged (0 on all-pass, 1 on any failure).
- Verification:
  - `php tests/run.php TestRunHistoryStoreTest ThemeRegistryTest` (baseline, all pass): `Summary: 10 run, 10 passed, 0 failed`, exit code 0.
  - Temporarily broke `ThemeRegistryTest::testThemeNamesAreUnique`, reran: `Summary: 9 passed, 1 failed`, listed under "New failures", exit code 1.
  - Reran again unchanged: same test listed under "Long-standing failures" as "(failing 2 runs, since <timestamp>)".
  - Reverted the break, reran: test listed under "Newly recovered"; confirmed `git diff tests/ThemeRegistryTest.php` is empty (clean revert).
  - `php -l tests/run.php` — no syntax errors.
- Notes:
  - Deleted the local `state/test_run_history.sqlite` created during manual smoke testing (gitignored, not part of the commit).

## Stage 4 - Filtered-run scoping and untracked-DB verification
- Changes:
  - `tests/TestRunnerBehaviorTest.php`: updated the two assertions that checked for the old `'All tests passed.'` string to instead check for the new `'Summary: 1 run, 1 passed, 0 failed'` line — this is the same output surface Stage 3 changed, so it's a same-surface fix, not new scope.
- Verification:
  - `php tests/run.php TestRunHistoryStoreTest::testFirstFailureIsClassifiedAsNewFailure` — summary correctly scoped to `1 run, 1 passed, 0 failed` (not the full suite count).
  - `php tests/run.php TestRunnerBehaviorTest` — all 4 tests pass after the assertion update.
  - Full-suite run (`php tests/run.php`, 497 tests): summary counts are internally consistent (`Summary: N failed` always matches the number of bullets under "Failing tests"/"New failures"). Found and fixed the `TestRunnerBehaviorTest` regression above via this run.
  - Cross-checked the 6 other full-suite failures (`LocalAppSmokeTest` activity/signature tests, `LazyComposeSigningTest`) against `main` in a scratch worktree (`git worktree add`) — confirmed all 6 are pre-existing failures unrelated to this feature, not caused by these changes. (A 7th, `WriteApiSmokeTest::testIncrementalApprovalMatchesFreshRebuildForTransitiveApprovalAndScoreRefresh`, appeared intermittently across runs — pre-existing flake, also unrelated.)
  - `git status --short` after the full run — `state/test_run_history.sqlite` does not appear; covered by the existing blanket `state/` gitignore entry, no new ignore rule needed.
- Notes:
  - No production code changes were needed; only the test-runner reporting file and its own behavior test.
