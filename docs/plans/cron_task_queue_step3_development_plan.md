# Cron Task Queue — Step 3: Development Plan

## Stage 1
- Goal: Add durable internal task lifecycle and safe deduplicated enqueueing.
- Dependencies: Approved Step 2 scope and a private runtime-state path writable by the web and cron users.
- Expected changes: Create a task-queue store in a private SQLite database, separate from the rebuildable read model; support allowlisted task types, queued/running/completed/failed state, bounded attempts, safe diagnostic fields, and one outstanding task per deduplication key. Planned contracts include `enqueue(string $type, string $deduplicationKey): Task` and `claimNext(int $limit): list<Task>`.
- Verification approach: Store tests cover duplicate enqueueing, atomic claiming, terminal-state recording, retry limits, and queue persistence while the read model is rebuilt.
- Risks or open questions:
  - Confirm the deployment’s web and cron users share access to the private queue path.
  - Set a conservative initial retry limit and define handling for interrupted running tasks.
- Canonical components/API contracts touched: New internal task-queue store; existing SQLite transaction conventions; no reuse of agent-reply or Codex-handoff stores.

## Stage 2
- Goal: Execute the initial allowlisted read-model rebuild task safely from a bounded worker.
- Dependencies: Stage 1 lifecycle and existing `ReadModelBuilder`/`ExecutionLock` behavior.
- Expected changes: Add a task dispatcher that accepts only registered internal handlers; implement `rebuild_read_model` by reusing the canonical rebuild workflow and its lock; record success, recoverable failure, or terminal failure without accepting command text or arbitrary payloads. Planned contract: `runClaimed(Task $task): TaskResult`.
- Verification approach: Worker tests prove one claimed rebuild completes, a concurrent worker exits cleanly, handler exceptions follow the bounded retry policy, and unknown task types never execute.
- Risks or open questions:
  - A rebuild may outlast a cron interval; the worker and rebuild lock must prevent duplicate execution.
  - Define a safe timeout/recovery policy for a task left running after process termination.
- Canonical components/API contracts touched: `ReadModelBuilder`, read-model `ExecutionLock`, and the new task-dispatch contract.

## Stage 3
- Goal: Provide the cron-facing command and operator controls for the queue.
- Dependencies: Stages 1–2.
- Expected changes: Add a `./v3` task-queue command family for enqueueing the initial approved task, bounded worker runs, dry runs, and status; add a cron-reference command and production/recovery runbook guidance using the configured repository, read-model, and private queue paths.
- Verification approach: Command tests cover argument validation, dry-run/status output, bounded processing, lock contention, and generated cron guidance; runbook commands match the command interface.
- Risks or open questions:
  - Operator commands must not disclose private paths or error detail in visitor-facing responses.
  - Cron must run as a user permitted to write both the private queue and the read-model state.
- Canonical components/API contracts touched: `v3` command router, existing cron-reference/worker conventions, production and operator-recovery runbooks.

## Stage 4
- Goal: Use the queue for read-model capability recovery without blocking visitors.
- Dependencies: Stages 1–3 and the Forte activity read-model compatibility direction.
- Expected changes: When a known missing read-model capability is detected, enqueue the deduplicated rebuild task and return the established safe degraded Activity/API outcome; extend read-model status with queue/recovery state for operators while keeping queue details private from visitors.
- Verification approach: A pre-feature read model serves ordinary activity, produces one outstanding rebuild task across repeated requests, exposes no SQL/PDO text, and returns full Commits behavior after the worker succeeds.
- Risks or open questions:
  - Enqueue failure must retain the safe recovery response and surface a distinct operator-visible status.
  - Queue status must not make a stale model appear ready before rebuild succeeds.
- Canonical components/API contracts touched: Forte Activity page and commit APIs, `FrontController` recovery response, `/api/read_model_status`, and the task-queue enqueue contract.
