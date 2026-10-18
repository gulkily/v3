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

## Enhancement Requests
- Request: Next action lists every failing indicator; aligned plain-word rows. / Disposition: in scope (Stages 4-5). / Approval: approved. / Outcome: pending.
- Request: Detect database locks. / Disposition: in scope (Stage 3). / Approval: approved. / Outcome: pending.
