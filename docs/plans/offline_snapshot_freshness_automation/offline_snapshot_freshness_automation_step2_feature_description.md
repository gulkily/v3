# Offline Snapshot Freshness Automation — Step 2 Feature Description

## Problem

The public offline snapshot can lag after successful public read-model changes
because publication is manual or coupled to static publication.

## User Stories

- As a reader, I want the downloadable offline snapshot to reflect recent public
  discussion so that offline reading is useful after reconnecting.
- As an operator, I want snapshot refreshes to run reliably in the background
  so that routine writes do not require manual publication.
- As an operator, I want failed or delayed refreshes to be visible and retryable
  so that I can restore freshness without guessing.

## Core Requirements

- Queue one deduplicated snapshot-publication request after every successful
  public read-model-changing write or rebuild.
- Keep the initiating write successful and responsive if later publication
  fails; retain the last valid snapshot.
- Reuse atomic publication of the bounded public snapshot; publication must not
  rebuild the read model or render static pages.
- Run a periodic guard that requests the same publication task at least every
  15 minutes, covering missed triggers and direct updates.
- Surface task state and failures through the existing operator task-queue
  status and retry behavior.

## Shared Component Inventory

- **Internal task queue and worker — extend:** canonical background-work,
  deduplication, locking, retry, and status surface; add snapshot publication as
  an allowlisted task.
- **Offline snapshot publisher — reuse:** remains the sole builder and atomic
  publisher of the public snapshot.
- **Public snapshot endpoint and offline reader — reuse unchanged:** continue
  to consume the existing published snapshot URL, with no new reader UI or API.
- **Task-queue CLI/status and cron integration — extend:** expose the new task
  consistently with existing operator-facing queued work and schedule the guard.

## User Flow

1. A public write or read-model rebuild completes successfully.
2. The application requests the coalesced snapshot-publication task.
3. The normal worker publishes one fresh snapshot and records the result.
4. The periodic guard requests the same task; it fills missed changes without
   duplicating outstanding work.
5. An operator inspects status or retries after a visible failure.

## Success Criteria

- A successful eligible update results in a newly published snapshot on the
  next normal worker run without manual `offline publish`.
- Bursts create no more than one outstanding snapshot-publication task.
- A failed publication does not fail the completed write and leaves the prior
  valid snapshot available.
- With no successful event trigger, the periodic guard restores or refreshes
  the snapshot within 15 minutes plus one worker interval.
