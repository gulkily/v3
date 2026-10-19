# Status: Frozen Frontend Diagnosis — Step 4 Implementation Summary

> **Feature plan:** [Step 1](./status_frozen_frontend_diagnosis_step1_solution_assessment.md) · [Step 2](./status_frozen_frontend_diagnosis_step2_feature_description.md) · [Step 3](./status_frozen_frontend_diagnosis_step3_development_plan.md) · [Step 4](./status_frozen_frontend_diagnosis_step4_implementation_summary.md)

Debug mode: enabled

Branch: `feature/status-frozen-frontend-diagnosis`. Planning commit: d2f51687.

## Stage 1 - Address discovery
- Changes:
  - Found that `scripts/dev_server.php` takes `--address=` and records no address or PID file, so status cannot discover it.
  - Decision: default `http://127.0.0.1:8000` plus `--url=` override ("address assumed" wording comes in Stage 2/5).
  - `scripts/status.php`: added validated `--url=` option and usage text; `v3` help line updated.
  - `tests/StatusCommandTest.php`: added `testStatusValidatesUrlOption`.
- Verification:
  - `php tests/run.php StatusCommandTest`: passed (6 run, 6 passed).
  - Manual: `./v3 status --help`, `--url=bogus` (error, exit 1), `--url=http://127.0.0.1:9` (accepted).
- Notes:
  - `--url` is parsed but not yet used; Stage 2 consumes it.

## Stage 2 - Frontend probe
- Changes:
  - New `src/ForumRewrite/Support/FrontendProbe.php`: `probe(string $url, float $timeoutSeconds = 3.0)` returns `not_running`, `timed_out`, `responding`, `error` (HTTP 5xx) or `unavailable` (bad URL) with detail and latency; hard overall deadline.
  - `OperatorStatusCollector` takes optional `?string $frontendUrl` and adds a `frontend` key (`not_checked` when null, so existing collector tests stay isolated); `scripts/status.php` passes `--url` or `http://127.0.0.1:8000`.
  - New `tests/FrontendProbeTest.php` (registered in `tests/run.php`): closed port, never-responding listener (bounded time), running PHP server, invalid URL.
- Verification:
  - `php tests/run.php FrontendProbeTest StatusCommandTest OperatorStatusCollectorTest`: passed (16 run, 16 passed).
- Notes:
  - Output is not rendered yet (Stage 5). Single-threaded dev server behind a stuck request shows as `timed_out`, matching the plan.

## Stage 3 - Database lock probe
- Changes:
  - New `src/ForumRewrite/Support/DatabaseLockProbe.php`: `probe(string $path, float $busyTimeoutSeconds = 1.0)` returns `accessible`, `locked` (reads blocked, or write lock held with reads working), `missing` or `unavailable`. Never creates the file; the `BEGIN IMMEDIATE` write attempt is rolled back immediately.
  - `OperatorStatusCollector` adds `database_access` for the read-model and task-queue databases.
  - New `tests/DatabaseLockProbeTest.php` (registered): missing file not created, accessible with unchanged file hash, write-lock holder, exclusive-lock holder, garbage file; bounded run time. Collector test asserts the new keys.
- Verification:
  - `php tests/run.php DatabaseLockProbeTest OperatorStatusCollectorTest StatusCommandTest`: passed (17 run, 17 passed).
- Notes:
  - The read-only fallback from the plan was not needed; the probe changed no data in the fixture test. A real writer may wait a few milliseconds if the probe's brief reserved lock lands first; to be confirmed in Stage 6.
  - Rebuild-aware wording belongs to Stage 4 (it combines this with the rebuild task state).

## Stage 4 - Indicator logic
- Changes:
  - New `src/ForumRewrite/Support/HealthIndicators.php`: `fromStatus(array $status)` returns rows (level OK/WARN/FAIL, label, detail, fix) for Frontend, Read model, Stale marker, Shared lock, Read-model DB, Queue DB, Rebuild task, Task executor, Task queue, Failed tasks; `fixes()` collects every non-OK fix.
  - Executor and backlog warn only when tasks are queued and the executor is not fresh/running (idle executor with empty queue is OK). Locked-database and shared-lock fixes say whether a rebuild is running ("wait") or not ("a process may be stuck").
  - Frontend row omitted when not checked; missing read-model DB is left to the Read model row; missing queue DB is OK ("not created yet").
  - New `tests/HealthIndicatorsTest.php` (registered): healthy, pasted case, idle executor, fresh executor with backlog, frontend verdicts, rebuild-aware lock text, shared lock/stale marker/failed tasks, unavailable read model.
- Verification:
  - `php tests/run.php HealthIndicatorsTest`: passed (8 run, 8 passed).
- Notes:
  - Not wired into output yet (Stage 5).

## Stage 5 - Output
- Changes:
  - `scripts/status.php`: prints an aligned `Health summary` (plain `OK`/`WARN`/`FAIL`, no brackets; level column 6 wide, label column padded to the longest label) before the unchanged existing lines; Next action lists every non-OK fix, one per line, and keeps today's single-line message when all OK.
  - Fix text reuses the existing sentences ("Run ./v3 task-queue enqueue-rebuild...", "A rebuild is queued...", "A queued-worker rebuild is running...") so existing assertions pass unchanged.
  - `tests/StatusCommandTest.php`: added `testStatusPrintsAlignedHealthSummaryAndEveryFix` (alignment, no brackets, FAIL frontend via `--url`, multi-line fixes, existing lines intact).
- Verification:
  - `php tests/run.php StatusCommandTest HealthIndicatorsTest`: passed. Initially two existing StatusCommandTest assertions failed on reworded Next action text; fixed by reusing the original wording (assertions unchanged).
  - `php tests/run.php TerminalOperatorUiCommandTest TerminalOperatorUiDashboardTest OperatorStatusCollectorTest HealthIndicatorsTest DatabaseLockProbeTest FrontendProbeTest`: passed (27 run, 27 passed).
  - Manual `./v3 status` on this machine (server running, 958 queued, no executor): OK for Frontend (HTTP 200), WARN for Task executor and Task queue, two Next action lines.
- Notes:
  - Full suite not run; focused runs only.

## Stage 6 - Acceptance replay
- Changes:
  - Replay found one defect: the "address assumed unless --url is given" text showed even when `--url` was passed. Removed it from the Frontend detail and moved the `--url=` hint into the Frontend fix text (`HealthIndicators.php`).
- Verification (manual, this WSL machine, against copies of the live read-model and queue databases in the scratchpad):
  - Server stopped (`--url=http://127.0.0.1:1`): `FAIL Frontend` (connection refused); Next action lists Frontend, Task executor, Task queue.
  - Server hung (listener that never answers): `FAIL Frontend` "no response within 3s (server busy or hung)"; status finished in 3.0s.
  - Server healthy (live dev server on :8000): `OK Frontend` HTTP 200.
  - Pasted case (queued tasks, executor `not_observed`; this machine has 958 queued): `WARN Task executor` and `WARN Task queue`, read model rows OK.
  - Read-model DB held by a writer, no rebuild: `FAIL Read-model DB`, fix "no rebuild running; a process may be stuck"; status took 1.1s (probe busy timeout).
  - Same with a running rebuild task: `FAIL Read-model DB` fix "locked while a rebuild is running; wait", plus `WARN Rebuild task running`.
  - `php tests/run.php StatusCommandTest HealthIndicatorsTest`: passed after the wording fix.
- Notes:
  - Not replayed: the operator's other machine (`v3-codex-3`) and a real in-progress rebuild process; the rebuild-running case used a claimed queue task plus a held lock.
  - Probe did not alter data: only rolled-back `BEGIN IMMEDIATE`; the live databases were only probed, copies were used for lock tests.
  - Full test suite not run; focused runs only.

## Final Verification
- Full suite `php tests/run.php`: 1273 run, 1271 passed, 2 failed. Both failures are long-standing baseline failures (failing since 2026-10-16, before this work) and unrelated to status: `ReadModelBuilderTimingTest::testProgressSeparatesParsingDerivationAndWritingWithBoundedCounts` and `WriteApiSmokeTest::testPreparedFeatureFlagChangeVerifiesSignatureAndCommitsActionEvidence`.
- Commits: planning d2f51687 plus six stage commits (cddfd620, 8be1d200, e45754f8, 56d68c94, e4f1570a, 1b5cb8aa); count 7 >= 1 + 6.
- Deployment/release/external checks: inapplicable (local CLI change). Not verified: the operator's `v3-codex-3` machine and a real in-progress rebuild process.

## Enhancement Requests
- Request: Next action lists every failing indicator; aligned plain-word rows. / Disposition: in scope (Stages 4-5). / Approval: approved. / Outcome: implemented in Stages 4-5 (commits 56d68c94, e4f1570a).
- Request: Detect database locks. / Disposition: in scope (Stage 3). / Approval: approved. / Outcome: implemented in Stages 3-4 (commits e45754f8, 56d68c94); lock-holder identity and lock age deferred.
