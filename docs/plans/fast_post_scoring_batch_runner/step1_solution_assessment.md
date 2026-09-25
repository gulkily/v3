# Fast Post Scoring Batch Runner Step 1 Solution Assessment

## Problem Statement

Operators need a safe command or scheduled worker that finds posts lacking a current fast score and rates them without reprocessing already current posts.

## Option A: Add a coalesced fast-score sweep to the existing task queue

Pros:
- Reuses the existing locking, retry, status, and cron conventions.
- Runs bounded batches while coalescing repeated sweep requests.
- Supports persistent score state keyed to post content and rubric revision.

Cons:
- Extends a queue currently limited to read-model rebuild work.
- Requires score freshness and retry semantics to be defined.

## Option B: Add a standalone `fast-score run` command

Pros:
- Smallest operator-facing path for one-off or manually scheduled backfills.
- Avoids expanding the shared task queue.

Cons:
- Needs separate locking, progress, retry, and cron behavior.
- Makes concurrent or repeated runs easier to overlap.

## Option C: Queue one scoring task per post

Pros:
- Gives each post independent retry history and visibility.
- Allows fine-grained prioritization in the future.

Cons:
- Creates many queue rows for a potentially large backlog.
- Requires robust deduplication when posts change or the rubric changes.

## Recommendation

Recommend Option A.

Brief justification:
- A coalesced sweep fits the existing operator queue and lets the worker process bounded batches without turning every historical post into a separate job.
- Step 2 should define eligibility, stored score/freshness identity, batch limits, failure behavior, and the operator command/cron contract.
