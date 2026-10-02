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

## Stage 4 - Safe classified visitor recovery

- Changes:
  - FrontController now narrowly recognizes only SQLite’s missing threads.vote_count error, requests the guarded existing rebuild task, and returns a safe 503 update/maintenance page.
  - A fresh executor heartbeat produces “site will be back soon”; stale, blocked, or queue-unavailable recovery produces maintenance copy.
  - Other escaped application failures now use a generic temporary-unavailability page rather than exposing exception details. Existing busy and preflight configuration pages remain unchanged.
- Verification:
  - php -l src/ForumRewrite/Host/FrontController.php && php -l tests/LocalAppSmokeTest.php
  - php tests/run.php LocalAppSmokeTest::testFrontControllerQueuesMissingVoteCountRecoveryWithoutLeakingSql LocalAppSmokeTest::testFrontControllerSanitizesUnexpectedApplicationFailure LocalAppSmokeTest::testFrontControllerShowsConfigurationErrorForMissingRepository LocalAppSmokeTest::testFrontControllerShowsBusyErrorForExecutionLockContention — 4 passed.
- Notes:
  - The stale-executor page deliberately does not claim that rebuilding began; Stage 5 adds the configured detached launcher for that path.

## Stage 5 - Bounded detached recovery fallback

- Changes:
  - Added an opt-in detached launcher that invokes only the application’s fixed queue-worker command with `nohup`, fully escaped executable/path arguments, private queue path, quiet output, and a one-task limit.
  - A stale or missing heartbeat now reserves exactly one launch record for the queued automatic-recovery task. It reports rebuilding only after that worker was started; disabled, unavailable, or failed launch states remain on truthful maintenance copy and are not retried by page refreshes.
  - The fallback is disabled unless `FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED=true`; its launch status is recorded in the private queue database.
- Verification:
  - `php tests/run.php TaskQueueStoreTest TaskQueueCommandTest DetachedTaskQueueLauncherTest LocalAppSmokeTest::testFrontControllerQueuesMissingVoteCountRecoveryWithoutLeakingSql LocalAppSmokeTest::testFrontControllerMakesOnlyOneDisabledFallbackLaunchAttempt LocalAppSmokeTest::testFrontControllerSanitizesUnexpectedApplicationFailure LocalAppSmokeTest::testFrontControllerShowsConfigurationErrorForMissingRepository LocalAppSmokeTest::testFrontControllerShowsBusyErrorForExecutionLockContention` — 30 passed.
  - Manual isolated detached launch with the opt-in environment variable returned `launched`; its independent worker recorded a fresh executor heartbeat in the temporary private queue database.
- Notes:
  - This is a best-effort host capability, not a claim that every PHP host permits background process creation. A host without it safely shows maintenance and preserves the queued task for the normal worker.
