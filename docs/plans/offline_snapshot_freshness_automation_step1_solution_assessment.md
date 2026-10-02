# Offline Snapshot Freshness Automation — Step 1 Solution Assessment

## Problem

The public offline snapshot is refreshed only during static publication or a
manual `offline publish`, so it can lag behind the current public read model.

## Option A — Host cron runs the publisher

- Pros: Smallest change; bounded freshness interval; uses the existing atomic
  publisher.
- Cons: Republishes when nothing changed; host-only locking, retries, and
  observability; no immediate response to a successful write.

## Option B — Publish synchronously after every live update

- Pros: Minimal content lag; simple freshness story.
- Cons: Adds snapshot-build latency and failure handling to write/rebuild
  paths; burst writes repeat expensive work; publication must not make a
  successful content write appear failed.

## Option C — Coalesced internal publication task with a periodic backstop

- Pros: Enqueue after successful read-model-changing writes/rebuilds, then let
  the existing locked task worker publish once for a burst; retain atomic
  publication, retry/status visibility, and a scheduled guard for direct or
  missed database updates.
- Cons: Requires a new internal task type, enqueue hooks, and operator
  configuration; freshness is eventual rather than synchronous.

## Recommendation

Choose **Option C**. It keeps writes responsive, publishes only from a ready
read model, and makes freshness failures observable and retryable. Use the
worker’s normal cadence for event-triggered work and a modest periodic guard
(for example, every 15 minutes) that enqueues the same deduplicated task; do
not rebuild the read model as part of snapshot publication.
