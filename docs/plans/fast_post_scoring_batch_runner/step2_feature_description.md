# Fast Post Scoring Batch Runner Step 2 Feature Description

## Problem

Fast scoring currently rates one requested post and forgets the outcome. Operators need a resumable batch process that records current ratings and safely processes the outstanding backlog.

## User Stories

- As an operator, I want to enqueue a fast-score sweep so that current posts are rated without manually supplying every post ID.
- As an operator, I want a bounded worker run and queue status so that I can control cost and monitor progress.
- As an operator, I want changed posts and changed rubrics to become eligible again so that stored ratings remain meaningful.
- As an operator, I want individual failures recorded without stopping the remaining batch so that temporary provider issues can be retried.
- As an evaluator, I want model exchanges associated with scored posts so that I can audit and calibrate outcomes.

## Core Requirements

- A sweep considers all current, nonempty root posts and replies; a score is current only when its post-content identity and active rubric revision both match.
- The worker processes no more than its configured batch limit, records each outcome and source, and continues after an individual non-score or provider failure.
- Repeated enqueue requests coalesce into one outstanding sweep; task-queue locking and retry behavior protect concurrent operators and cron runs.
- The operator can enqueue, run, inspect, and schedule the sweep through the existing task-queue command family; the existing one-post score API remains available for diagnostics.
- Reader-facing post cards and ranking behavior remain unchanged; batch score data is private operational state and model calls continue to use the existing exchange audit surface.

## Shared Component Inventory

- `./v3 task-queue` enqueue/run/status/cron: extend as the canonical batch-control surface rather than add a parallel worker command.
- Existing task-queue status and codebase queue summaries: extend to report fast-score sweep work through their canonical task data.
- `POST /api/score_post`: retain as the canonical one-post diagnostic contract; do not turn it into a batch endpoint.
- Private LLM-exchanges tool and post-card exchange links: reuse for model-call inspection; no new score UI is needed.

## Simple User Flow

1. The operator enables fast scoring and supplies a rubric with defined 0 and 1 meanings.
2. The operator enqueues a fast-score sweep through `./v3 task-queue`.
3. A manual worker run or cron claims the coalesced sweep and rates one bounded batch of outstanding posts.
4. The worker records completed, excluded, and failed outcomes, then reports progress through queue status.
5. A later sweep skips current scores and picks up changed, failed, or newly created eligible posts.

## Success Criteria

- A fresh sweep rates every eligible unscored post over successive bounded runs and does not re-rate current posts.
- Editing a post or changing the rubric revision makes only the affected score eligible again.
- Duplicate enqueue attempts create at most one outstanding sweep, and concurrent workers do not process the same claimed task.
- One provider failure is recorded and does not prevent other eligible posts in that batch from being processed.
- Operators can inspect task status and a model exchange for a scored post without exposing scores to readers.
