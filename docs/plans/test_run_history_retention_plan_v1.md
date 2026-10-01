# Test run history retention

## Progress

- [ ] Decision: pick retention model (rolling-window column vs. append-only
      log table) and retention size (X runs / Y days / both)
- [ ] Schema change (new column or new table + migration for the existing
      `state/test_run_history.sqlite`)
- [ ] Write path: record each run's result into the retained history, not
      just the current-state counters
- [ ] Retention/pruning: enforce the X-run or Y-day cutoff so the table
      can't grow unboundedly
- [ ] Expose the retained history (at minimum a `getHistory($testName)`
      method; optionally surface it in `printRunSummary()`'s report)
- [ ] Unit tests mirroring `TestRunHistoryStoreTest.php`'s existing style
      (schema migration, write, prune, read)
- [ ] Full-suite dry run against a scratch copy of the real
      `state/test_run_history.sqlite`, same as done for the recovery-window
      change

Not started — this file is a plan only, written per user request after
estimating the effort in conversation.

## Context

`tests/Support/TestRunHistoryStore.php` (`test_run_results` table) is a
**snapshot table**, not a log: one row per test name, `PRIMARY KEY
test_name`, upserted on every run. It currently tracks, per test, only:

- `last_status`, `last_run_at`, `last_changed_at`
- `consecutive_fail_count` / `consecutive_pass_count` (current streak only)
- `first_failed_at` (start of the *current* fail streak, cleared on
  recovery)
- `recovered_at` (start of the *current* pass streak, cleared if it fails
  again)

This is enough to classify each run (`new_failure`, `long_standing_failure`,
`recovered`, `recently_recovered` — see the recovery-highlight-window work
in this same file) but it cannot answer "what did this test do 3 days ago"
or "how often has this test flaked over the last N runs" — once a streak
ends, everything about it is gone except the counters.

The user asked how much effort it would take to retain real history (X
runs or Y days) instead. This plan captures that estimate so it can be
picked up later without re-deriving it.

## Options

### Option A — rolling window column (bounded, no new table)

Store the last N `{run_at, status}` pairs as a JSON array in the existing
`test_run_results` row, shifting out the oldest entry each time a new one
is appended.

- Row count never grows past the current 605 (one per test) — no pruning
  job needed beyond capping the JSON array length.
- Supports "last X runs" cleanly. Does **not** cleanly support "last Y
  days" (a day cutoff would have to look inside the JSON blob).
- Querying requires app-level JSON decode; no real SQL filtering across
  tests (e.g. no cheap "which tests failed more than twice in the last 10
  runs" query).
- **Estimate: ~1–1.5 hours.**

### Option B — append-only log table (proper history)

New table, e.g.:

```sql
CREATE TABLE test_run_log (
    test_name TEXT NOT NULL,
    run_at TEXT NOT NULL,
    status TEXT NOT NULL
);
CREATE INDEX idx_test_run_log_test_name_run_at ON test_run_log (test_name, run_at);
```

One row inserted per test per run, alongside the existing
`test_run_results` upsert (that summary table stays as-is and keeps
driving the fast classification logic — no need to touch it).

Breakdown:

1. New table + migration for the existing DB file — reuse the same
   `CREATE TABLE IF NOT EXISTS` / `PRAGMA table_info` guard pattern already
   used for the `consecutive_pass_count`/`recovered_at` migration.
   **~15 min.**
2. Write path: one `INSERT` per test per run, wrapped in a transaction (605
   inserts/run is trivial for SQLite, but batching avoids 605 separate
   fsyncs). **~20 min.**
3. Retention/pruning — pick one or both:
   - Count-based: keep only the last X rows per `test_name`. Needs
     `ROW_NUMBER() OVER (PARTITION BY test_name ORDER BY run_at DESC)` (SQLite
     ≥ 3.25, confirm the PHP build's bundled SQLite supports window
     functions) and delete anything ranked past X.
   - Day-based: `DELETE FROM test_run_log WHERE run_at < :cutoff`. Trivial.
   - Whichever is chosen, run it every full-suite run (same place
     `pruneStaleEntries()` runs today).
   **~30 min**, plus the decision above.
4. A way to actually use the data — right now nothing would read it. At
   minimum a `getHistory(string $testName, int $limit): array` method.
   Anything shown in the CLI report (e.g. a flakiness indicator, a per-test
   timeline) is a real UX decision, not just plumbing — budget separately.
   **~30–90 min** depending on how much is surfaced.
5. Tests mirroring the existing `TestRunHistoryStoreTest.php` style (insert,
   prune-by-count, prune-by-day, read). **~30–45 min.**

**Estimate: ~2–3 hours**, not counting open-ended report/UX work in step 4.

### Storage growth (Option B only — Option A is bounded by construction)

This environment's history shows very frequent runs — the "76 runs since
2026-09-25" counters observed in conversation imply roughly one full-suite
run every 15–20 minutes. At 605 tests/run that's ~45–60k rows/day. A
30-day retention window would land around 1.3–1.8M rows — likely low tens
of MB on SQLite, not alarming, but a hard row-count cap is worth adding
regardless of the day cutoff, in case run frequency increases later.

## Recommendation

Unless calendar-day queries ("show me what happened last week") are
actually wanted, Option A (rolling window column) is cheaper, simpler, and
structurally can't grow unboundedly. Option B is the right call only if
day-based retention or cross-test SQL queries over history are genuinely
needed.

## Open questions for whoever picks this up

- Runs, days, or both? (Changes which option applies and the pruning SQL.)
- If days: what's Y? If runs: what's X?
- Does the report (`printRunSummary()`) need to *display* anything from
  this history, or is "retain it for later querying" enough on its own?
