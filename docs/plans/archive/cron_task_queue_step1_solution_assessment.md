# Cron Task Queue — Step 1: Solution Assessment

## Problem statement

The application needs a durable way to enqueue internal maintenance work, such as read-model recovery, for a bounded cron worker to execute outside a visitor request.

## Option A — Schedule each maintenance command directly in cron

Pros:
- Smallest operational change for one known task.
- No new queue abstraction or task lifecycle to maintain.

Cons:
- Cannot record, deduplicate, prioritize, or inspect work requested by the application.
- Repeats work on every schedule and does not provide a durable handoff from request/deploy state to cron.

## Option B — Generalize an existing agent-reply or Codex-handoff queue

Pros:
- Reuses existing SQLite claims, cron locking, and worker conventions.
- Avoids introducing another independently operated queue.

Cons:
- Conflates unrelated authorization, payload, retention, and failure semantics.
- Risks exposing a general maintenance task through workflows intended for user-requested agent work.

## Option C — Add a dedicated internal task queue with an allowlisted cron worker

Pros:
- Gives application and deployment code a durable, inspectable, deduplicated handoff for approved maintenance task types.
- Keeps task execution bounded, auditable, and separate from visitor requests and specialized agent queues.
- Provides a reusable foundation for read-model rebuild/recovery and future internal tasks.

Cons:
- Introduces another persistent lifecycle, worker command, operational status surface, and cron entry.
- Needs deliberately narrow task types and retry rules to avoid becoming an unrestricted remote command mechanism.

## Recommendation

**Recommend Option C.** A dedicated internal queue best matches the requirement while preserving the specialized queues’ boundaries. Its initial scope should be a small allowlist of idempotent maintenance tasks, with the read-model rebuild/recovery use case first; arbitrary commands and user-supplied task payloads are explicitly out of scope.
