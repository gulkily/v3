# Cron Task Queue — Step 4: Implementation Summary

## Stage 1 - Durable task lifecycle
- Changes:
  - Added a private SQLite-backed internal task store, independent of the rebuildable read model.
  - Restricted initial enqueueing to `rebuild_read_model`, with outstanding-task deduplication, atomic claims, bounded attempts, terminal results, and abandoned-claim recovery.
  - Added focused task-store coverage to the test runner.
- Verification:
  - `php -l src/ForumRewrite/TaskQueue/SqliteTaskQueueStore.php`
  - `php -l tests/TaskQueueStoreTest.php`
  - `php tests/run.php TaskQueueStoreTest` — 5 tests passed.
- Notes:
  - Queue rows remain in their own database, so a read-model rebuild cannot erase a claimed task or its outcome.

## Stage 2 - Allowlisted rebuild worker
- Changes:
  - Added the bounded task worker and an explicit `rebuild_read_model` dispatch path.
  - Added the rebuild handler, which reuses the canonical rebuild builder, stale-marker clearing, and read-model execution lock.
  - Unknown tasks now become terminal failures without invoking a handler; rebuild failures retry only within the queue’s attempt limit.
- Verification:
  - `php -l src/ForumRewrite/TaskQueue/TaskQueueWorker.php`
  - `php -l src/ForumRewrite/TaskQueue/ReadModelRebuildTaskHandler.php`
  - `php -l tests/TaskQueueWorkerTest.php`
  - `php tests/run.php TaskQueueStoreTest TaskQueueWorkerTest` — 8 tests passed.
- Notes:
  - A disposable live-rebuild smoke command was not run because its temporary-directory cleanup was blocked by the execution environment; the focused tests cover successful dispatch, retry/failure bounds, and refusal of unknown tasks.
