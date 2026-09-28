# Step 2: Feature Description — Test Run Summary Report

## Problem
`tests/run.php` prints per-test PASS/FAIL lines and a slow-test list, but no run summary — you can't see totals at a glance, or whether a failure is new or has been failing for a while.

## User Stories
- As a developer running `./v3 test`, I want a summary of pass/fail counts so I don't have to scroll/count PASS/FAIL lines.
- As a developer, I want the slowest tests called out so I can spot regressions in test performance.
- As a developer, I want failing tests listed together (name + error) so I can triage quickly.
- As a developer, I want to know which failures are new (introduced since the last run) versus long-standing, so I know what I broke versus pre-existing debt.
- As a developer, I want to see tests that just started passing again (recovered), so I can confirm a fix landed.

## Core Requirements
- After every `tests/run.php` run, print a summary block with: total run, total passed, total failed.
- List all currently failing tests with their error message.
- List slow tests (reuse/extend the existing threshold-based slow-test report).
- Persist per-test run history in a local SQLite database (untracked/gitignored) and use it to classify each failing test as "new failure" or "failing for N runs since <date>", and to list tests that changed from failing to passing ("newly recovered").
- Summary must print regardless of pass/fail outcome (currently the runner exits early in some paths); must not change the process exit code behavior (still exit 1 on any failure).

## Shared Component Inventory
- `tests/run.php` already owns all run-time reporting (PASS/FAIL lines, `printSlowTestsOverThreshold`) — this is the single existing surface that renders test results. The summary feature extends `tests/run.php` directly; no other component renders test output, so nothing is forked.
- No existing local SQLite storage/query helper is test-runner-specific; project has SQLite usage elsewhere (e.g. `SqliteQueryCatalogTest`, `SqliteLlmExchangeStoreTest`) but those are app read-model stores, not applicable to reuse here — a small dedicated history store is needed.

## Simple User Flow
1. Developer runs `./v3 test` (or `php tests/run.php` with optional filters).
2. Each test executes as today (PASS/FAIL line per test, unchanged).
3. Runner records this run's per-test results into the local SQLite history store.
4. Runner prints a summary block: counts (run/passed/failed), slow tests, failing tests with errors, and a history section (new failures, long-standing failures with streak/since-date, newly recovered tests).
5. Runner exits 0 if all passed, 1 if any failed — unchanged from current behavior.

## Success Criteria
- Running the suite prints exact pass/failed/total counts matching the PASS/FAIL lines emitted.
- A failing test seen for the first time is labeled "new failure"; a failing test seen failing in the prior run is labeled with a streak count and first-failed date.
- A test that failed last run and passes this run appears in a "recovered" list.
- Filtered runs (single class/method via `shouldRunClass`/`shouldRunMethod`) still produce a correct summary scoped to the tests actually run.
- No SQLite history file is committed to the repo (git-ignored).
