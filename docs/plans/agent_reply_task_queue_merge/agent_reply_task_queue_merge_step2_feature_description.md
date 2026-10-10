# Agent Reply Task Queue Merge Step 2 Feature Description

> **Feature plan:** [Step 1](./agent_reply_task_queue_merge_step1_solution_assessment.md) · [Step 2](./agent_reply_task_queue_merge_step2_feature_description.md) · [Step 3](./agent_reply_task_queue_merge_step3_development_plan.md) · [Step 4](./agent_reply_task_queue_merge_step4_implementation_summary.md)

## Problem

Agent replies and maintenance work currently use separate queues, workers, locks, cron entries, and operational status paths. Operators need one general-purpose queue for all allowlisted background work while preserving agent-reply safety and recoverability.

## User Stories

- As an operator, I want each requested agent reply represented by one deduplicated general-purpose task so that I can manage all background work through one queue.
- As an operator, I want one worker and cron contract so that queue execution cannot overlap or be missed because a separate worker was not installed.
- As an operator, I want agent-reply request and result diagnostics retained so that I can investigate a skipped, failed, or published reply.

## Core Requirements

- A newly accepted agent-reply request enqueues one corresponding general-purpose task; repeat requests for the same outstanding reply do not add work.
- The general-purpose worker fulfills agent-reply tasks through the existing reply gates, reservation, and idempotent publication lifecycle.
- General-purpose queue claiming, retry/recovery, locking, history, and status become the sole execution mechanism for agent-reply work.
- Existing agent-reply status remains available for reply-specific outcomes; operator commands and documentation lead to the unified queue workflow.
- Pending work is preserved during the transition, with no duplicate reply publication or silently abandoned request.

## Delivery Scope

- Work type: application change.

## Completion Boundary

- Normal entry: an approved user requests an eligible agent reply.
- End-to-end outcome: one general-purpose queue task is visible and the unified worker publishes, skips, or records a recoverable failure for that reply.
- Needed recovery: an operator can inspect and retry or otherwise resolve failed/abandoned task work without a separate agent-reply worker.
- Release condition: the separate agent-reply worker/cron execution path is retired, and queue/reply-focused verification demonstrates no duplicate or lost requests.

## Risks

- **Duplicate publication during transition** — validate with a request submitted while the worker runs; mitigate with stable per-reply task identity and existing posting reservation before Step 3.
- **Stalled or lost existing requests** — validate migration/restart behavior using pending and abandoned work; mitigate with explicit transition accounting and recovery rules before Step 3.
- **Maintenance work starvation or reply latency** — validate mixed queue ordering under bounded worker runs; mitigate with defined fairness and task limits before Step 3.
- **Reduced operational visibility** — validate operator status for queued, failed, skipped, and published replies; mitigate by retaining reply-specific diagnostics and updating the canonical queue status surface.

## Shared Component Inventory

- `./v3 task-queue` commands and task-queue worker: extend as the canonical enqueue, run, cron, and status surface.
- `./v3 agent-reply` commands and reply status: retain/adjust only for reply-specific diagnostics and compatibility; do not retain a separate worker path.
- Approved-post-card agent-reply request flow: reuse as the canonical request entry; it continues to return quickly after creating durable work.
- Deployment runbook and CLI reference: extend the canonical task-queue operations guidance; remove separate agent-reply cron guidance.
- No existing browser UI renders queue-task state; no new UI is required for this operator-focused slice.

## Simple User Flow

1. An approved user requests an agent reply from an eligible post.
2. The request creates or reuses the reply record and its single general-purpose task.
3. The installed general-purpose worker claims the task and applies the normal reply lifecycle.
4. The operator sees queue execution in task-queue status and investigates reply-specific outcome details when needed.

## Success Criteria

- Each outstanding requested reply has exactly one active general-purpose task.
- A single installed worker/cron contract processes both maintenance and agent-reply work.
- Repeated requests and worker recovery do not produce duplicate `reply-agent` posts.
- Operators can identify queued, completed, skipped, failed, and recoverable agent-reply work from the unified operations path.
