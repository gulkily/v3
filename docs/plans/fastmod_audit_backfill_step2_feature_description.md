# Fastmod Audit and Backfill Step 2 Feature Description

## Problem

Fastmod intentionally rates only newly published content, so operators cannot
reliably measure the historical backlog or make a bounded, informed decision to
rate it.

## User stories

- As an operator, I want a read-only Fastmod audit so that I know historical
  candidate counts, current outcome states, and an estimated provider cost.
- As an operator, I want an explicit, bounded historical backfill so that I can
  rate selected existing content without accidentally creating a corpus sweep.
- As an operator, I want normal Fastmod status and retries to include backfill
  work so that recovery uses the established workflow.

## Core requirements

- Audit is read-only: it creates no score, work, task-queue, or exchange row.
- Audit reports total historical candidates, already-scored/excluded/pending
  content, and a transparent estimated-cost range for the configured provider.
- Backfill requires explicit historical scope, confirmation, and operator-set
  volume and spend bounds.
- Backfill reuses existing Fastmod deduplication, retry, failure, retention,
  and score-display behavior; it never changes normal new-content processing.
- Backfill is opt-in and disabled by default; no rebuild, rubric change, or
  ordinary queue run may create historical work.

## Shared component inventory

- `./v3 fast-score`: extend as the canonical operator surface for audit,
  backfill initiation, status, retry, and prune.
- Task queue and Fastmod worker: reuse for bounded provider work; preserve its
  existing new-content behavior.
- Private Fastmod score/work state: reuse for outcome, retry, and retention
  tracking; do not introduce public score storage.
- `/posts/{id}` Fastmod display: reuse unchanged for successfully scored posts.
- LLM-exchange audit surface: reuse unchanged for provider-call inspection.

## User flow

1. The operator runs a read-only historical audit.
2. The operator reviews candidate and cost estimates.
3. The operator starts a confirmed, bounded historical backfill.
4. The normal task worker processes it; the operator monitors or retries via
   existing Fastmod status controls.

## Success criteria

- An audit supplies actionable count and cost estimates without writing state.
- A backfill cannot begin without explicit confirmation and bounds.
- A bounded backfill creates no more work than authorized and remains
  diagnosable through existing status and exchange surfaces.
- New-post Fastmod enqueueing remains unchanged.

## Next

If this feature description is accepted, reply **Approved Step 2**. I will
then create the Step 3 development plan.
