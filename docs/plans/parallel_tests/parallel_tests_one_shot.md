Cycle: `v3/parallel_tests`; Mode: one-shot; Record/index: this file, [plans index](../README.md); follows [`smoke_tests`](../smoke_tests/smoke_tests_one_shot.md) (PR #18, merged); revisits Option B of the superseded [`fast_default_tests` Step 1](../fast_default_tests_step1_solution_assessment.md)
Debug mode: enabled
Effort study: `railspike-effort` / `v3/parallel_tests`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.
Extended estimates: enabled (no alternatives or stages exist, so only the cycle estimate applies)
Debug report: [log](parallel_tests_debug_log.md)

**Cycle estimate: 0.45**

Status: committed and pushed on `feat/parallel-tests`; next permitted action: maintainer's first full parallel run, then review; merge only on request.

## Original request

2026-11-04, maintainer: "i think we should still do a parallel runner, wdyt?" Claude recommended it for the maintainer's periodic full run if the usual machine has enough cores, proposed `./v3 test --all --jobs=N`, and noted that verifying it would mean running the full suite. Maintainer: "10 cores. test --all --jobs=N sounds good, and can you please make a "fulltest" alias".

## Authorization

Explicit one-shot for `--jobs=N` and a `fulltest` alias. The standing rule that agents do not run the full suite stays in force, since the maintainer did not grant the exception Claude asked about. Verification therefore uses parallel runs of the smoke set and of the heaviest classes, and the first full parallel run is left to the maintainer. Exclusions: changing what tests check, merge or deploy.

## Outcome and work type

Application work in the test runner, the history store, one test's isolation, runner tests and docs. Work item `thread-20261104124801-959cf5dfd6c6920287120a7c6c35c6fd` (claimed; takes effect once this workspace's signing identity is approved).

## Approach and risks

The coordinator plans units from the selected tests: a class is one unit; a class whose recorded time exceeds 30 s is split into method chunks of about 20 s. Units are sorted slowest first and run in up to N worker processes, which are ordinary runner processes given explicit names. Each worker writes its results to a temporary file named by `FORUM_TEST_WORKER_RESULTS`; the coordinator prints each finished unit's output whole (no interleaving), then records history and prints the summary once. History gains `last_duration_seconds`, migrated in place, and every run records durations, so scheduling improves as history fills. Risks: tests sharing state when run at the same time (mitigated by stress runs; one found and fixed), a worker crash losing results (reported as a failure for that unit), and nested runners started by tests (handled by removing the variable from the worker's environment).

## Concepts touched

None in the concept inventory.

## Verification and recovery plan

Runner and history tests; parallel smoke set; two parallel runs of the 16 heaviest classes; the alias with named classes; a serial smoke run. Recovery: without `--jobs`, the runner runs serially as before.

## Starting revisions and branch

`main` at `e291a20b` (#18 merged); branch `feat/parallel-tests`. The branch was first cut from `feat/smoke-tests` before #18 merged and was rebased onto `main` with no conflicts.

## Dated execution history

- 2026-11-04 — Discovery: test history recorded pass/fail but no durations, contrary to the parked Step 1's note that it "already records durations". Added the column and `durations()`.
- 2026-11-04 — Bug found and fixed during verification: `TestRunnerBehaviorTest` starts nested runners, which inherited `FORUM_TEST_WORKER_RESULTS` and acted as workers (5 failures in the first parallel smoke run). A worker now reads the variable and removes it from its environment.
- 2026-11-04 — Clash found and fixed: `LocalAppSmokeTest::testRebuildDiagnosisAndRecoveryArchiveSQLiteSidecarsBeforeRebuilding` kept its database directly in the system temp folder, so its rebuild lock was `/tmp/forum-rewrite.lock`, shared with every other test doing the same. In parallel, another worker's write held it and diagnose rightly reported "lock held". The test now uses its own directory. `CONTRIBUTING.md` tells test authors to do the same. Other tests that keep databases directly in `/tmp` still share that lock; in the stress runs this only serialized their writes, and no further failures appeared.
- 2026-11-04 — `./v3 fulltest` = `tests/run.php --all --jobs=<cores>` (`nproc`, falling back to `sysctl -n hw.ncpu`, then 4); a later `--jobs=N` overrides. Docs: `CONTRIBUTING.md` table and parallel-safety note, `docs/reference/v3_cli.md`, `README.md`, `AGENTS.md` (names `fulltest` as the full run agents must not wait for), `./v3` help.

## Study history

- Intake: missing (discussion preceded rating). Execution entry: **0.45**, scope v1, single exposed Claude rater. Closeout retrospective: 0.45. Not hours or measured labor; maintainer acceptance pending.

## Outcome

`./v3 fulltest` runs the full suite across every core. On this 2-core workspace with 4 workers, the 16 heaviest classes (about 335 s serially) finished in 136 s, and the smoke set in 20 s instead of 44 s. On a 10-core machine the full suite should approach the length of its longest unit (about 20–30 s) plus startup; not measured.

## Verification

- `TestRunnerBehaviorTest` 8/8 (new parallel case: results, multi-line failure detail, summary, exit codes); `TestRunHistoryStoreTest` 9/9 (new duration and migration case).
- Parallel smoke set, `--jobs=4`: 427/427 after the nested-runner fix (first run: 5 failures, the bug above).
- 16 heaviest classes, `--jobs=4`: first run 467/468 (the lock clash above); after the fix 468/468 in 136 s, 29 units.
- `./v3 fulltest --jobs=2 WorkItemsTest TestRunnerBehaviorTest`: 14/14.
- Serial smoke set (`./v3 test`): 428/428, 42 s.
- Not run: the full suite in parallel or serially, per the standing rule; the maintainer's first `./v3 fulltest` is the remaining check, and any further clash it shows is most likely another shared path like the lock above.

## Commit, merge and publication status

Committed and pushed on `feat/parallel-tests`; PR opened against `main`. Not merged or deployed.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
