# Status: Frozen Frontend Diagnosis — Step 3 Development Plan

> **Feature plan:** [Step 1](./status_frozen_frontend_diagnosis_step1_solution_assessment.md) · [Step 2](./status_frozen_frontend_diagnosis_step2_feature_description.md) · [Step 3](./status_frozen_frontend_diagnosis_step3_development_plan.md) · [Step 4](./status_frozen_frontend_diagnosis_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract
- Entry: `./v3 status` (optional `--url=` for a non-default server address).
- Outcome: aligned `OK`/`WARN`/`FAIL` Health summary above the unchanged existing lines; Next action lists every failing indicator with its fix.
- Recovery: operator follows each Next action line and reruns status until all `OK`.
- Deployment/external verification: none; manual check on this WSL machine against stopped, healthy, and hung `./v3 start` servers, plus replay of the pasted case (130 queued, executor `not_observed`).
- Release: new and existing status tests pass; manual replays recorded in the Step 4 summary.

## Key Risks
- **Wrong address:** custom port gives false FAIL. Validate in Stage 1 by reading `local_server_start.sh`; mitigate with default `127.0.0.1:8000`, `--url=`, and "address assumed" wording.
- **Blocked probe:** stuck dev server stalls the request. Validate in Stage 2 with a hung fixture; mitigate with a 3s timeout reported as "busy or hung".
- **Lock probe side effects:** write probe could disturb a live writer. Validate in Stage 3 with a writer-holding fixture; mitigate with 1s busy timeout, immediate rollback, read-only fallback.
- **False alarms:** idle executor with empty queue must not warn. Validate in Stage 4 tests.
- **Output compatibility:** existing lines must not change. Validate in Stage 5 with the existing status tests unchanged.

## Stage 1
- Goal: Settle how the frontend address is found.
- Dependencies: none.
- Expected changes: none to code; note findings in the Step 4 summary. Add optional `--url=` to the status option parser and usage text.
- Verification approach: read `scripts/local_server_start.sh`; run `./v3 status --help` and a bad `--url=` case.
- Risks or open questions:
  - Impact: server may not record its address.
  - Early warning / validation: no address file found.
  - Mitigation: use default plus `--url=` only.
- Canonical components/API contracts touched: `scripts/status.php` option parsing.

## Stage 2
- Goal: Frontend probe returning not running / timed out / responding with latency.
- Dependencies: Stage 1.
- Expected changes: new `FrontendProbe` support class, `probe(string $url, float $timeoutSeconds): array{status:string,detail:string,latency_ms:?int}`; wired into `OperatorStatusCollector` as a new `frontend` key.
- Verification approach: unit tests with a local listener, a closed port, and a listener that never responds.
- Risks or open questions:
  - Impact: probe could hang status.
  - Early warning / validation: test exceeds timeout.
  - Mitigation: hard connect and read timeouts.
- Canonical components/API contracts touched: `OperatorStatusCollector::collect()` result shape (additive key).

## Stage 3
- Goal: Detect blocked databases.
- Dependencies: Stage 1.
- Expected changes: new `DatabaseLockProbe::probe(string $path, float $busyTimeoutSeconds): array{status:string,detail:string}` returning accessible, locked, or unavailable; collector adds `database_access` for the read-model and task-queue databases.
- Verification approach: tests with a fixture holding a write transaction, a missing file, and a free database; confirm no data change and bounded run time.
- Risks or open questions:
  - Impact: probe disturbs a live writer or hangs.
  - Early warning / validation: fixture writer fails or test exceeds timeout.
  - Mitigation: 1s busy timeout, immediate rollback, read-only fallback.
- Canonical components/API contracts touched: `OperatorStatusCollector::collect()` result shape (additive key); `ReadModelConnection` reused for the read-model open.

## Stage 4
- Goal: Derive the indicator list from collected data.
- Dependencies: Stages 2 and 3.
- Expected changes: new `HealthIndicators::fromStatus(array $status): list<array{level:string,label:string,detail:string,fix:?string}>` covering frontend, read model, stale marker, shared lock, database access (per database, with rebuild-aware fix text), rebuild task, executor, queue backlog, failed tasks.
- Verification approach: table tests per indicator, including idle executor with empty queue (OK) and the pasted case (WARN, WARN).
- Risks or open questions:
  - Impact: noisy or missed warnings.
  - Early warning / validation: test table disagreement.
  - Mitigation: WARN only when queued > 0 and executor is not fresh or running.
- Canonical components/API contracts touched: collector output (read only).

## Stage 5
- Goal: Render the Health summary and multi-line Next action.
- Dependencies: Stage 4.
- Expected changes: `printStatus` prints aligned plain-word rows (padded columns, no brackets) before existing lines; `statusNextAction` lists every WARN/FAIL fix, else keeps today's message.
- Verification approach: extend `StatusCommandTest` for alignment and fix lines; existing assertions unchanged and passing.
- Risks or open questions:
  - Impact: existing output regressions.
  - Early warning / validation: existing tests fail.
  - Mitigation: only prepend and replace the Next action text for failing cases.
- Canonical components/API contracts touched: `scripts/status.php` output.

## Stage 6
- Goal: Replay reported failures as acceptance evidence.
- Dependencies: Stage 5.
- Expected changes: none to code (fixes only if the replay finds defects); record results in the Step 4 summary.
- Verification approach: on this WSL machine run `./v3 status` with the server stopped, running, and hung, and with a database held by a writer during a simulated rebuild and with no rebuild; replay the pasted queue and executor case against a copy of that state.
- Risks or open questions:
  - Impact: real server behaves differently from fixtures.
  - Early warning / validation: verdict mismatch.
  - Mitigation: adjust the probe and add a regression test.
- Canonical components/API contracts touched: none.
