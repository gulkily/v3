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

## Stage 3 - Cron and operator commands
- Changes:
  - Added private queue-path configuration, the `./v3 task-queue` command family, and a cron-reference command.
  - Added enqueue, bounded worker-run, dry-run, and status operations using a non-overlapping queue-worker lock.
  - Documented the private queue path, cron worker, and queue-assisted recovery in production and operator runbooks.
- Verification:
  - `php -l src/ForumRewrite/TaskQueue/TaskQueueDatabaseConfig.php`
  - `php -l scripts/task_queue.php`
  - `php -l tests/TaskQueueCommandTest.php`
  - `bash -n v3`
  - `php tests/run.php TaskQueueStoreTest TaskQueueWorkerTest TaskQueueCommandTest` — 12 tests passed.
  - `./v3 task-queue cron --log=/tmp/forum-task-queue-smoke.log` — printed the expected once-per-minute bounded worker entry; it did not modify crontab.
- Notes:
  - The queue database path may be overridden with `FORUM_TASK_QUEUE_DATABASE_PATH`; its default remains private runtime state.
