# Cron Task Queue — Step 2: Feature Description

## Problem

Maintenance work that cannot safely run in a visitor request needs a durable handoff to cron. Today, there is no shared internal queue for work such as rebuilding a stale read model, so recovery depends on manual operator action.

## User stories

- As the application, I want to enqueue an approved maintenance task so that the request can return without performing expensive work.
- As an operator, I want cron to claim and process a bounded number of queued tasks so that maintenance is reliable and does not overlap.
- As an operator, I want to inspect queued, running, completed, and failed work so that I can diagnose and recover from failures.
- As a visitor, I want delayed maintenance to be handled safely in the background so that I receive a clear temporary outcome instead of an internal error.

## Core requirements

- Accept only an explicit allowlist of internal, idempotent task types; initial scope includes read-model rebuild/recovery.
- Persist task requests and outcomes durably, deduplicate equivalent outstanding work, and retain enough safe context for an operator to understand the result.
- Provide a bounded, non-overlapping cron worker that claims tasks safely and records completion or failure.
- Make retry behavior explicit and bounded; failures must remain visible rather than looping indefinitely.
- Exclude arbitrary shell commands, visitor-supplied command payloads, and replacement of the existing specialized agent-reply or Codex-handoff queues.

## Shared component inventory

- **Existing cron-worker commands and `ExecutionLock`:** reuse their canonical locking, bounded-run, quiet-output, and operational conventions for the new worker.
- **Agent-reply and Codex-handoff stores/workers:** do not extend; they remain specialized queues whose authorization and payload rules differ from internal maintenance work.
- **Read-model rebuild workflow:** reuse as the initial approved task handler; do not duplicate its data-production behavior.
- **`/api/read_model_status` and existing operator recovery guidance:** extend as the canonical visibility and recovery surfaces for rebuild-required and queued-work state.
- **Visitor-facing read-model recovery response:** reuse the compatibility feature’s safe temporary-state experience; it should enqueue or reflect work without exposing queue internals.

## User flow

1. The application or deployment process identifies an approved maintenance need and enqueues one task.
2. A visitor receives the normal safe temporary response while that work is pending.
3. Cron starts the worker, safely claims a limited batch, and executes each approved task.
4. The worker records success or a visible, bounded failure outcome.
5. An operator checks existing status/recovery surfaces; after successful rebuild, the affected feature works on retry.

## Success criteria

- Equivalent outstanding maintenance requests result in one durable queued task, not duplicate concurrent work.
- A cron run processes only the configured bounded batch, does not overlap another worker, and records a terminal outcome for every claimed task.
- Read-model recovery can be requested without doing the rebuild in a web request, and a successful worker run restores the affected capability.
- Failed work is observable with a safe reason and follows its defined retry limit.
- No route or queue path accepts arbitrary commands or lets visitors enqueue privileged maintenance work.
