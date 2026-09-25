# Step 1: Solution Assessment — Test Run Summary Report

## Problem
`tests/run.php` currently prints per-test PASS/FAIL lines and a slow-test report, but gives no run summary (pass/fail counts) and no way to tell which failures are new versus long-standing, which requires persisting results across runs.

## Option A — Append-only JSON run log
- Each run appends a record (`{timestamp, results: {testName: pass|fail, durationSeconds}}`) to `tests/.test_run_history.jsonl`.
- Stats (streaks, "new failure", "recovered") computed by scanning back through the log at report time.
- Pros: simple to append; full history preserved for future analysis.
- Cons: unbounded growth needs manual/periodic trimming; scanning full log gets slower over time; more moving parts to compute streaks.

## Option B — Compact per-test state file (updated in place)
- Single JSON file `tests/.test_run_state.json` keyed by test name: `{status, consecutiveFailCount, firstFailedAt, lastChangedAt}`.
- Each run diffs current results against stored state, updates it in place, and derives "new failure" / "new pass (recovered)" / "long-standing failure (N runs / since date)" directly from the diff.
- Pros: file size stays constant; no scan/aggregation step; streak and first-failure-date are cheap O(1) lookups; matches guardrail against adding DB schema/infra.
- Cons: no full history if deeper analysis (e.g., flakiness over time) is wanted later; only last-known state is kept.

## Option C — SQLite table for run history
- Reuse project's existing SQLite patterns (`state/`, `SqliteQueryCatalogTest`, etc.) to store one row per test per run in a local SQLite DB.
- Pros: queryable with SQL, easy to extend with richer stats later (flakiness rate, duration trends).
- Cons: heavier for the ask; introduces a new local DB file to manage/gitignore; overkill for "pass/fail + streak" requirement; project guardrail says avoid DB schema changes when feasible.

## Decision
**Option C** (SQLite table for run history), per user request. The SQLite file (e.g. `state/test_run_history.sqlite`) stays local/untracked (gitignored), not committed to the repo.
