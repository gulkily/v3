# Step 4: Read-Model Failure Experience — Implementation Summary

## Stage 1 - Queue executor lifecycle history

- Changes:
  - Added private `task_queue_executor_runs` history to the existing task-queue SQLite database, with safe run status, timestamps, aggregate results, and task outcomes.
  - Each non-dry queue-worker invocation now records a run, including quiet and empty runs; unexpected worker exceptions receive a safe failure code.
  - Added coverage for store-level run history and a quiet empty CLI worker run.
- Verification:
  - `php -l src/ForumRewrite/TaskQueue/SqliteTaskQueueStore.php && php -l scripts/task_queue.php && php -l tests/TaskQueueStoreTest.php && php -l tests/TaskQueueCommandTest.php`
  - `php tests/run.php TaskQueueStoreTest TaskQueueCommandTest` — 18 passed.
  - Manual isolated queue run: `php scripts/task_queue.php run --quiet --queue-database-path=<temporary path>` followed by private history inspection returned `status=completed claimed=0 outcomes=0`.
- Notes:
  - This is durable application-private history, not a `/var/log` dependency. Status presentation, liveness evaluation, and retention arrive in later stages.

## Stage 2 - Executor liveness and status

- Changes:
  - Added a two-minute executor-heartbeat freshness window derived from the latest completed private queue run.
  - Extended task-queue CLI status, `./v3 status`, and `/api/read_model_status` with safe executor status; they distinguish fresh, stale, running, failed, and not-observed state without claiming cron is installed.
  - Added fresh/stale heartbeat and operator-status coverage.
- Verification:
  - `php -l src/ForumRewrite/TaskQueue/SqliteTaskQueueStore.php && php -l src/ForumRewrite/Support/OperatorStatusCollector.php && php -l src/ForumRewrite/Http/CodebaseStateController.php && php -l scripts/task_queue.php && php -l scripts/status.php && php -l tests/TaskQueueStoreTest.php && php -l tests/OperatorStatusCollectorTest.php`
  - `php tests/run.php TaskQueueStoreTest TaskQueueCommandTest OperatorStatusCollectorTest StatusCommandTest` — 28 passed.
  - Manual isolated quiet run followed by `php scripts/task_queue.php status --queue-database-path=<temporary path>` reported `Executor: fresh`.
- Notes:
  - A fresh heartbeat proves the queue worker ran successfully; it intentionally does not assert a host cron entry exists.

## Stage 3 - Automatic-recovery gate

- Changes:
  - Added reason-scoped automatic rebuild state in the private queue database, reusing the existing `rebuild_read_model` task and its deduplication key.
  - Terminal task failures, including abandoned final attempts, now open a circuit that blocks later automatic recovery requests until an operator resets it.
  - Added `./v3 task-queue reset-recovery` as the explicit operator-only reset path.
- Verification:
  - `php tests/run.php TaskQueueStoreTest TaskQueueCommandTest TaskQueueWorkerTest` — 29 passed.
  - Manual isolated recovery: forced a one-attempt rebuild failure (`blocked`), ran `./v3 task-queue reset-recovery`, and confirmed state became `idle`.
- Notes:
  - Manual `enqueue-rebuild` remains unchanged. The circuit is used only by the forthcoming classified automatic-recovery path.
