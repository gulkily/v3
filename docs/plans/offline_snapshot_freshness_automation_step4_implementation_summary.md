# Offline Snapshot Freshness Automation — Step 4 Implementation Summary

## Stage 1 - Add queue primitive and worker dispatch
- Changes:
  - Added the allowlisted `publish_offline_snapshot` task type.
  - Extended the task worker with a retryable offline-snapshot publisher handler.
  - Added task-store and worker coverage for coalescing, completion, and failure retries.
- Verification:
  - `php -l` passed for the queue store, worker, and their focused tests.
  - `php tests/run.php TaskQueueStoreTest TaskQueueWorkerTest` passed: 15 run, 15 passed.
- Notes:
  - No production command, write hook, or scheduler is wired in this stage.

## Stage 2 - Wire queue execution and manual enqueueing
- Changes:
  - Added `./v3 task-queue enqueue-offline-snapshot` with the canonical offline-snapshot deduplication key.
  - Wired the queue worker to the existing atomic publisher using configured read-model and static-root paths.
  - Preserved approved-members-only publication refusal in the queued path.
- Verification:
  - `php -l scripts/task_queue.php` and `php -l tests/TaskQueueCommandTest.php` passed; `bash -n v3` passed.
  - `php tests/run.php TaskQueueCommandTest TaskQueueStoreTest TaskQueueWorkerTest OfflineSnapshotPublisherTest` passed: 25 run, 25 passed.
- Notes:
  - The command enables safe manual recovery; event hooks and the periodic guard follow in later stages.

## Stage 3 - Enqueue after ready read-model changes
- Changes:
  - Routed successful committed web writes through a best-effort, deduplicated offline-snapshot enqueue after their read-model synchronization completes.
  - Made queued read-model rebuild completion request the same publication task.
  - Kept enqueue failures out of successful write and rebuild results, logging them for operator diagnosis instead.
- Verification:
  - `php -l` passed for `LocalWriteService`, `RouteServices`, `ReadModelRebuildTaskHandler`, `scripts/task_queue.php`, and `WriteApiSmokeTest.php`.
  - `php tests/run.php WriteApiSmokeTest::testNewPublishedPostEnqueuesPrivateBackgroundWork TaskQueueCommandTest TaskQueueWorkerTest` passed: 16 run, 16 passed.
- Notes:
  - Direct operator updates remain covered by the periodic guard added in Stage 4.

## Stage 4 - Add the periodic freshness guard
- Changes:
  - Extended `task-queue cron` to print both the once-per-minute worker and a 15-minute offline-snapshot enqueue guard.
  - Documented the new enqueue command, coalescing behavior, schedules, and manual recovery path in the CLI and offline/production runbooks.
- Verification:
  - `php -l scripts/task_queue.php` and `php -l tests/TaskQueueCommandTest.php` passed.
  - `php tests/run.php TaskQueueCommandTest` passed: 8 run, 8 passed.
  - Manual smoke: `./v3 task-queue cron --log=/tmp/forum-task-queue-test.log` printed the per-minute worker and `*/15` guard lines.
- Notes:
  - The guard only enqueues; the normal worker performs atomic publication, so concurrent triggers remain coalesced.

## Stage 5 - Verify freshness behavior and operator handoff
- Changes:
  - Completed focused regression coverage across event enqueueing, task coalescing/retry, queued atomic publication, snapshot diagnostics, and the documented cron guard.
- Verification:
  - `php tests/run.php OfflineSnapshotPublisherTest OfflineSnapshotPublishCommandTest OfflineReadingDiagnosticCommandTest TaskQueueStoreTest TaskQueueCommandTest TaskQueueWorkerTest WriteApiSmokeTest::testNewPublishedPostEnqueuesPrivateBackgroundWork ForteActivityReadModelRecoveryTest` passed: 32 run, 32 passed.
- Notes:
  - The generated cron reference must be installed with both lines: the worker every minute and the snapshot enqueue guard every 15 minutes.
