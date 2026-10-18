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

## Enhancement Requests
- Request: Next action lists every failing indicator; aligned plain-word rows. / Disposition: in scope (Stages 4-5). / Approval: approved. / Outcome: pending.
- Request: Detect database locks. / Disposition: in scope (Stage 3). / Approval: approved. / Outcome: pending.
