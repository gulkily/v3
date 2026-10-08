# Step 3: Read-Model Failure Experience — Development Plan

## Stage 1 — Queue executor lifecycle history

- **Goal:** Record every queue-worker start and terminal outcome, including quiet/empty cron runs.
- **Dependencies:** Existing task-queue CLI and private queue database.
- **Expected changes:** Add safe executor-run history beside tasks: timestamp, run ID, aggregate outcome, and task IDs/outcomes without SQL, paths, or exception payloads.
- **Verification:** Run an empty queue and a rebuild task; confirm private queue history has start/finish entries.
- **Risks/open questions:** The shared cron/web user must write private queue state; retain bounded history and no public details.
- **Contracts:** `SqliteTaskQueueStore`, `scripts/task_queue.php` run/cron, task-queue status.

## Stage 2 — Durable executor liveness and operator status

- **Goal:** Determine whether the executor has completed successfully within a defined freshness window.
- **Dependencies:** Stage 1; private task-queue state.
- **Expected changes:** Persist executor heartbeat/outcome metadata with queue state; extend `./v3 status`, `./v3 task-queue status`, and safe `/api/read_model_status` aggregate fields.
- **Verification:** Run a worker, inspect fresh status, then use a controlled stale fixture and confirm stale status/next action.
- **Risks/open questions:** Status must distinguish “not observed” from “unhealthy” without claiming cron installation.
- **Contracts:** `SqliteTaskQueueStore`, `OperatorStatusCollector`, `CodebaseStateController`.

## Stage 3 — Automatic-recovery gate

- **Goal:** Coalesce known recovery requests and stop automatic attempts after bounded terminal failure.
- **Dependencies:** Existing task attempts and Stage 2 state.
- **Expected changes:** Add conceptual recovery state/query APIs such as `requestAutomaticRebuild(string $reason): RecoveryRequest` and `resetAutomaticRecovery(): void`; record one reason-scoped circuit state and operator-reset outcome.
- **Verification:** Repeated requests produce one task; exhausted failure blocks another request; reset permits one new request.
- **Risks/open questions:** Reset authority remains CLI/operator-only; unrelated manual rebuild behavior must remain unchanged.
- **Contracts:** `SqliteTaskQueueStore`, `TaskQueueWorker`, rebuild-task status.

## Stage 4 — Safe classified visitor recovery

- **Goal:** Turn the missing-read-model-column failure into safe queued-recovery responses.
- **Dependencies:** Stages 2–3 and the canonical FrontController presenter.
- **Expected changes:** Add a narrow failure classifier and recovery-response presenter; fresh executor requests/reuses the task and returns 503 “back soon,” while blocked recovery returns maintenance; generic failures remain sanitized.
- **Verification:** Fixture with missing `threads.vote_count` contains no SQL/path text, queues once, and preserves busy/configuration paths.
- **Risks/open questions:** Detection must not classify arbitrary database failures as recoverable schema drift.
- **Contracts:** `FrontController`, `renderBusyError()`, task-queue recovery API.

## Stage 5 — Detached recovery launcher

- **Goal:** When executor liveness is stale, make one opt-in, disconnected-client-safe worker launch.
- **Dependencies:** Stages 2–4; deployment-provided launcher configuration and permission to run the approved CLI worker.
- **Expected changes:** Add a narrow launcher adapter with a planned `launchQueuedWorker(): LaunchResult` contract; it can invoke only the configured queue worker, records safe launch result, and is circuit-gated.
- **Verification:** Launcher test double confirms one invocation; a real supported-host smoke test confirms work continues after the request ends.
- **Risks/open questions:** Some hosts kill or prohibit child processes; unavailable launcher must leave an actionable maintenance state, not pretend recovery began.
- **Contracts:** FrontController recovery coordinator, task-queue CLI worker, private deployment configuration.

## Stage 6 — Progress, retention, and recovery verification

- **Goal:** Make background rebuilding observable without making it resumable/public and prove recovery manually.
- **Dependencies:** Stages 1–5; existing `ReadModelBuilder` progress reporter.
- **Expected changes:** Route safe build phase/checkpoint events to private queue history/status; add bounded retention for terminal tasks/run events and document heartbeat, launcher prerequisites, circuit reset, and manual recovery. Preserve atomic candidate rebuild semantics.
- **Verification:** On isolated paths, exercise fresh-heartbeat queueing, stale-heartbeat detached launch after client disconnect, terminal-failure circuit/reset, and an unrelated sanitized failure; verify ordered checkpoints, bounded history, and matching docs.
- **Risks/open questions:** Checkpoint volume must remain bounded; pruning cannot remove active/circuit state or make a partial candidate live.
- **Contracts:** `ReadModelRebuildTaskHandler`, `ReadModelBuilder` progress reporter, `SqliteTaskQueueStore`, operator runbooks.
