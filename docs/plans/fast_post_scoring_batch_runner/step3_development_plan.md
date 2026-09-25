# Fast Post Scoring Batch Runner Step 3 Development Plan

## Stage 1
- Goal: Persist fast-score outcomes with a freshness identity.
- Dependencies: Approved Step 2 requirements.
- Expected changes: Add a private score store keyed by post ID, post-content identity, and active rubric revision; record status, nullable probability, source, signals, and failure details. No reader-facing schema or migration is planned.
- Verification approach: Unit-test completed, excluded, failed, and stale/current score persistence.
- Risks or open questions:
  - The rubric revision must change whenever its probability meaning changes.
- Canonical components/API contracts touched: Existing analysis-store content-hash conventions; private operational SQLite state.

## Stage 2
- Goal: Find eligible posts without re-scoring current results.
- Dependencies: Stage 1 store; read-model post data.
- Expected changes: Add `FastScoreSweepService::run(int $postLimit): array` to select current nonempty roots and replies missing a matching stored score, then invoke the existing fast-score workflow for a bounded batch.
- Verification approach: Unit-test root/reply eligibility, changed-content and changed-rubric re-rating, batch limits, and continuation after individual failures.
- Risks or open questions:
  - Candidate order must be stable so repeated bounded runs eventually cover the backlog.
- Canonical components/API contracts touched: `FastScoreWorkflowService`, `FastScoreContextFactory`, and read-model post lookup conventions.

## Stage 3
- Goal: Run one coalesced sweep safely through the task queue.
- Dependencies: Stages 1–2; task-queue claim and retry behavior.
- Expected changes: Add a fast-score-sweep task type and worker dispatch; requeue the claimed sweep when eligible posts remain, and preserve task-level retry only for worker-level failures.
- Verification approach: Unit-test coalesced enqueueing, claimed-task dispatch, bounded continuation, and unsupported-task compatibility.
- Risks or open questions:
  - A provider failure belongs to the affected score record, not to the whole sweep task.
- Canonical components/API contracts touched: `SqliteTaskQueueStore`, `TaskQueueWorker`, and queue status records.

## Stage 4
- Goal: Expose sweep control through the existing task-queue CLI.
- Dependencies: Stage 3 queue contract.
- Expected changes: Add `./v3 task-queue enqueue-fast-score` and a score-batch limit option to `run`; include sweep progress in normal worker output, status, usage, and cron documentation.
- Verification approach: CLI smoke-test enqueue coalescing, dry-run, bounded worker output, and status reporting with a temporary database.
- Risks or open questions:
  - Keep task-worker `--limit` distinct from the number of posts scored per sweep.
- Canonical components/API contracts touched: `v3`, `scripts/task_queue.php`, and `docs/reference/v3_cli.md`.

## Stage 5
- Goal: Complete regression coverage and operator handoff.
- Dependencies: Stages 1–4.
- Expected changes: Add store, sweep, queue, CLI, and regression tests; update fast-scoring documentation with persistence/freshness and scheduling behavior.
- Verification approach: Run targeted tests, then the project suite; manually enqueue, run, inspect status, and confirm a recorded model exchange when a configured provider is available.
- Risks or open questions:
  - No reader-facing score display or ranking behavior is introduced.
- Canonical components/API contracts touched: Fast-scoring reference, LLM-exchanges audit tool, and test runner.
