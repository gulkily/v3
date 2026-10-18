# Status: Frozen Frontend Diagnosis — Step 2 Feature Description

> **Feature plan:** [Step 1](./status_frozen_frontend_diagnosis_step1_solution_assessment.md) · [Step 2](./status_frozen_frontend_diagnosis_step2_feature_description.md) · [Step 3](./status_frozen_frontend_diagnosis_step3_development_plan.md) · [Step 4](./status_frozen_frontend_diagnosis_step4_implementation_summary.md)

Debug mode: enabled

## Problem
`./v3 status` reports "No read-model recovery is currently required" even when the frontend is frozen, because it only checks read-model health. Common failures (server down or hung, no task executor, backlog) are not visible at a glance.

## User Stories
- As an operator with a frozen frontend, I want a good/bad indicator set at the top of `./v3 status` so that I can see the likely failure in seconds.
- As an operator, I want all existing status lines kept so that I still have the technical detail.
- As an operator, I want a Next action naming the first failing indicator so that I know what to run.

## Core Requirements
- Existing output lines stay unchanged; indicators are purely additive.
- Indicators use plain `OK` / `WARN` / `FAIL` words (no square brackets) for: frontend responding, read model ready, no stale marker, shared lock free, read-model database accessible, task-queue database accessible, rebuild task state, task executor observed, queue not backlogged, no failed tasks.
- Database access check opens each database with a short busy timeout and attempts a trivial read and a write-lock probe; a blocked database is reported by name, and Next action says whether a rebuild is the likely cause (rebuild running: wait; no rebuild: a process may be stuck). It never blocks status beyond that timeout and never modifies data.
- Indicator rows are aligned in vertical columns (status word, label, detail), padded to a fixed width.
- Frontend check is a short-timeout request to the local server and reports not running, timed out, or responding; it can never hang `status`.
- Next action lists every failing (WARN or FAIL) indicator with its fix, one per line; with all OK it keeps today's message.
- Status exit code and options remain compatible.

## Delivery Scope
- Work type: application change (status script/collector and tests).

## Completion Boundary
- Entry: `./v3 status` (optionally `--url`/address for non-default server).
- Outcome: indicator block plus unchanged detail; each failure maps to a distinct indicator and Next action.
- Recovery: operator follows Next action (start server, start worker, rebuild) and reruns status to see `[OK]`.
- Release: tests pass for each verdict; manual check against a stopped, a running, and a hung dev server.

## Known User or Environment
Operator on WSL2 (Linux) running `./v3 start`, default `127.0.0.1:8000`, whose browser shows a frozen page. Mismatch handled: server not running, hung, or worker absent with queued tasks. Next action is printed by status; the operator reruns `./v3 status` to verify. Evidence: indicator lines and probe latency on this WSL setup (the pasted case: 130 queued, executor `not_observed`, now `[WARN]`).

## Risks
- **Address discovery:** server may use a custom port or `--listen-all`; probe the wrong address and report a false `[FAIL]`. Validate early by checking how `./v3 start` records its address; mitigate with a default plus an override option, and say "address assumed" in output.
- **Single-threaded dev server:** a stuck request may block the probe; mitigate with a short timeout reported as "timed out (busy or hung)", not "down".
- **Lock probe side effects:** a write-lock probe could itself contend with or disturb a live writer. Validate early with a fixture holding a writer; mitigate with a short timeout, an immediately rolled-back probe, and read-only fallback if the probe cannot be made safe.
- **False alarms:** an idle executor with an empty queue must not warn; warn only on queued > 0 with no heartbeat. Cover with tests.

## Shared Component Inventory
- `OperatorStatusCollector` and `scripts/status.php` already render all status data; extend them (indicators derived from the collected data plus one new probe). No new surface.
- `./v3 task-queue status` and `fast-score status` stay as detail commands; no change.

## Simple User Flow
1. Operator runs `./v3 status`.
2. Indicator block prints with the frontend probe result.
3. Existing detail prints below, unchanged.
4. Next action names the first failing indicator.
5. Operator fixes it and reruns to see all `[OK]`.

## Success Criteria
- Stopped, hung, and healthy servers produce `[FAIL]`, `[FAIL]`/`[WARN]` (timeout), and `[OK]` respectively.
- The pasted case (queued 130, executor not observed) shows `[WARN]` for executor and backlog.
- `status` finishes within the probe timeout plus current runtime.
- Existing status tests pass unchanged; new tests cover each indicator.

## Enhancement Requests
- Request: Detect database locks, whether from rebuild or otherwise. / Disposition: in scope, added to requirements above; lock-holder identity and lock-age remain deferred (no timestamp is recorded today). / Approval status: approved (renewed Approved Step 2). / Outcome: n/a until Step 4.
- Request: Next action names every failing indicator; align indicators vertically; no square brackets. / Disposition: in scope, incorporated above (requirements). / Approval status: approved (Approved Step 2). / Outcome: n/a until Step 4.

Please review and reply `Approved Step 2` to proceed to Step 3, or request revisions.
