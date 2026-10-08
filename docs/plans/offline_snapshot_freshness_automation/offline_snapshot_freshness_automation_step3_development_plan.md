# Offline Snapshot Freshness Automation — Step 3 Development Plan

## Stage 1
- Goal: Add one deduplicated, allowlisted offline-snapshot publication task to the internal queue.
- Dependencies: Existing `OfflineSnapshotPublisher`, `SqliteTaskQueueStore`, and `TaskQueueWorker`.
- Expected changes: Add an offline-snapshot task type and fixed deduplication key; extend the worker constructor and dispatch with `callable(): void $publishOfflineSnapshot`; preserve normal queued/running deduplication and retry semantics.
- Verification approach: Store and worker tests prove task acceptance, coalescing, successful completion, and retryable publication failure.
- Risks or open questions:
  - The task must retain the existing queue's allowlist boundary.
- Canonical components/API contracts touched: `SqliteTaskQueueStore`, `TaskQueueWorker`, `OfflineSnapshotPublisher`.

## Stage 2
- Goal: Make queue execution and manual enqueueing publish from the configured ready read model.
- Dependencies: Stage 1; configured repository, read-model, static-root, and feature-flag paths.
- Expected changes: Add `./v3 task-queue enqueue-offline-snapshot`; wire the task worker's publisher callback to the existing publisher, preserving approved-members-only behavior and task status output.
- Verification approach: CLI tests prove enqueue coalescing and a worker run atomically creates a snapshot; failure output remains inspectable through `status`.
- Risks or open questions:
  - Resolve static-root configuration through the same canonical path used by `offline publish`.
- Canonical components/API contracts touched: `scripts/task_queue.php`, `v3`, `OfflineSnapshotPublisher`, task-queue CLI/status.

## Stage 3
- Goal: Request publication after every successful public read-model-changing write or rebuild without extending write success latency with publishing.
- Dependencies: Stage 1; the existing post-rebuild success paths.
- Expected changes: Introduce an enqueue boundary (for example, `enqueueOfflineSnapshotPublication(): void`) and call it only after a ready read model is successfully produced by `LocalWriteService` and queued/manual rebuild paths; catch and log enqueue infrastructure failures without changing an already-successful write result.
- Verification approach: Focused write/rebuild tests prove one outstanding task is requested after successful changes, no task follows a failed refresh, and an enqueue failure does not turn a successful write into an error.
- Risks or open questions:
  - Audit all rebuild entry points so none bypasses the event trigger.
  - Identity-only changes must be included only when they change the public snapshot's read model.
- Canonical components/API contracts touched: `LocalWriteService`, `ReadModelRebuildTaskHandler`, command rebuild paths, `SqliteTaskQueueStore`.

## Stage 4
- Goal: Add the periodic 15-minute freshness backstop using the same queue task.
- Dependencies: Stage 2's manual enqueue command and existing cron-reference contract.
- Expected changes: Extend the operator cron reference/configuration with a 15-minute `enqueue-offline-snapshot` invocation while retaining the normal once-per-minute queue worker; document the bounded freshness target and recovery path.
- Verification approach: Command/reference tests assert both schedules and that the guard uses the deduplicated enqueue command, not direct publication.
- Risks or open questions:
  - Operators must install both generated schedule entries for the documented bound to apply.
- Canonical components/API contracts touched: `task-queue cron` output, CLI reference, production-deploy and offline-reading runbooks.

## Stage 5
- Goal: Verify end-to-end freshness behavior and hand off operator guidance.
- Dependencies: Stages 1–4.
- Expected changes: Add focused integration coverage and concise documentation for event-triggered refresh, coalescing, failure/retry, and periodic recovery.
- Verification approach: Run the focused queue, publisher, command, write/rebuild, and offline diagnostic tests; manually inspect task status after a publish.
- Risks or open questions:
  - Confirm the selected production queue cadence meets the 15-minute-plus-one-worker-interval target.
- Canonical components/API contracts touched: task-queue status, offline diagnostic command, deployment and recovery runbooks.
