# Agent Reply Task Queue Merge Step 3 Development Plan

> **Feature plan:** [Step 1](./agent_reply_task_queue_merge_step1_solution_assessment.md) · [Step 2](./agent_reply_task_queue_merge_step2_feature_description.md) · [Step 3](./agent_reply_task_queue_merge_step3_development_plan.md) · [Step 4](./agent_reply_task_queue_merge_step4_implementation_summary.md)

## Completion Contract

- Normal entry: an approved-user reply request creates or reuses one `agent_reply` general-purpose task.
- End-to-end outcome: `./v3 task-queue run` processes that task through the existing reply lifecycle and records a terminal queue/reply outcome.
- Required recovery: abandoned or retryable task work recovers through the task queue; operators use task status plus reply diagnostics to resolve failures.
- Deployment/external verification: replace the separate agent-reply cron with the task-queue cron, verify one configured-provider reply when credentials are available, and confirm no old worker is scheduled.
- Release condition: no runtime path claims agent-reply requests outside the general-purpose queue, and focused plus full test suites pass.

## Key Risks

- **High risk: duplicate or lost request during enqueue transition.** Early validation: repeat-request and restart tests. Mitigation: stable per-reply task identity, idempotent reply reservation, and reconciliation of outstanding requests.
- **High risk: mismatched retries.** Early validation: provider failure and abandoned-task tests. Mitigation: define terminal skipped outcomes versus retryable execution failures before wiring the handler.
- **High risk: operator installs both workers.** Early validation: CLI/runbook assertions and deployment review. Mitigation: remove the separate execution command/cron reference and publish the single canonical cron contract.
- **High risk: maintenance work delays replies.** Early validation: mixed-task bounded-run test. Mitigation: retain FIFO claim order and document the worker limit/cron cadence.

## Stage 1
- Goal: Define `agent_reply` as an allowlisted general-purpose task with a stable identity for one reply request.
- Dependencies: Approved Step 2.
- Expected changes: Add the task type, task-key construction/parsing contract, and queue-store validation; task persistence remains in the existing general-purpose queue database.
- Verification approach: Store tests prove valid per-reply enqueue, duplicate coalescing while queued/running, and rejection of malformed task identities.
- Risks or open questions:
  - Impact: A non-stable key could create duplicate work.
  - Early warning / validation: Repeat enqueue produces more than one active task.
  - Mitigation: Use the target post plus immutable requested content identity as the key.
- Canonical components/API contracts touched: `SqliteTaskQueueStore`; its typed enqueue/deduplication contract.

## Stage 2
- Goal: Make the unified worker fulfill one claimed agent-reply task safely.
- Dependencies: Stage 1.
- Expected changes: Add an agent-reply handler to `TaskQueueWorker` and its script wiring; map successful publication and safe skips to completion, and only retryable execution faults to queue retry.
- Verification approach: Worker tests cover generated, already-posted, skipped, retryable failure, non-retryable failure, and abandoned-task recovery outcomes.
- Risks or open questions:
  - Impact: Queue status can diverge from reply state.
  - Early warning / validation: Terminal worker outcomes do not match reply diagnostics.
  - Mitigation: Delegate to the existing reply fulfillment lifecycle and record concise task progress.
- Canonical components/API contracts touched: `TaskQueueWorker`; `Application::fulfillAgentReplyRequest()`; agent-reply generation-store lifecycle.

## Stage 3
- Goal: Queue every new reply request and safely account for requests created before the merge.
- Dependencies: Stages 1–2.
- Expected changes: Extend the canonical approved-user request flow to enqueue its task; add bounded reconciliation for outstanding request rows without active tasks, including transition-safe replay after an enqueue interruption.
- Verification approach: End-to-end request tests prove one active task per reply, repeated request coalescing, reconciliation of legacy outstanding work, and no duplicate publication after restart.
- Risks or open questions:
  - Impact: A durable request could lack a task after a partial failure.
  - Early warning / validation: Outstanding reply diagnostics have no matching active/completing task.
  - Mitigation: Reconciliation deterministically recreates only the matching deduplicated task.
- Canonical components/API contracts touched: approved post-card request API; `PostWorkflowService`; `SqliteAgentReplyGenerationStore`; task-queue enqueue contract.

## Stage 4
- Goal: Retire the separate agent-reply worker execution path in favor of task-queue operations.
- Dependencies: Stage 3.
- Expected changes: Remove or redirect agent-reply worker/cron commands and dedicated lock behavior; extend task-queue run/status output with reply-task progress while retaining reply-only diagnostics.
- Verification approach: CLI tests prove a single worker/cron path; mixed maintenance/reply runs prove bounded FIFO execution and recoverable failures.
- Risks or open questions:
  - Impact: An old cron invocation could still execute duplicate fulfillment.
  - Early warning / validation: Deprecated command remains capable of claiming requests.
  - Mitigation: Make the old execution path unavailable and document the replacement command.
- Canonical components/API contracts touched: `./v3 task-queue`; `scripts/task_queue.php`; `./v3 agent-reply`; reply-status command.

## Stage 5
- Goal: Align operator documentation and complete release verification.
- Dependencies: Stage 4.
- Expected changes: Update CLI reference, production deployment guidance, examples, and tests to describe the one-queue cron/install, status, recovery, and legacy-request transition.
- Verification approach: Run focused queue/reply command, store, worker, and request tests; run the full suite; perform one configured-provider task-queue reply smoke test when credentials are available; inspect scheduled cron before release.
- Risks or open questions:
  - Impact: Operators may retain an obsolete cron entry.
  - Early warning / validation: Documentation exposes two fulfillment commands.
  - Mitigation: Make task-queue guidance canonical and explicitly remove the obsolete worker instructions.
- Canonical components/API contracts touched: `docs/reference/v3_cli.md`; `docs/runbooks/production_deploy.md`; queue and agent-reply test suites.
