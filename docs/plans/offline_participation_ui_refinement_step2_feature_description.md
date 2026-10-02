# Offline Participation UI Refinement Step 2 Feature Description

## Problem

Supported offline participation currently looks unlike the online forum and
requires manual delivery, making local work less familiar and less useful.

## User stories

- As a reader, I want saved thread titles, bodies, metadata, and Like controls
  to look like their online equivalents so that offline reading feels familiar.
- As a participant, I want to Like a thread or comment offline so that my
  signed intent is retained until it can be integrated.
- As a participant, I want queued work to send after reconnection so that I do
  not have to revisit every item manually.
- As a participant, I want a compact Outbox I can expand so that I can scan
  pending work without losing its detail or outcome.

## Core requirements

- Follow the online title/body, metadata, card, and Like-control presentation
  contract for supported snapshot views; do not introduce offline-only action
  affordances or remove equivalent visible presentation elements.
- Support offline Likes for visible thread roots and comments. Create a stable,
  detached-signed intent at click time; do not change local scores or claim
  server acceptance.
- Automatically process explicitly queued items in an open foreground page on
  reconnect or next online load. Drafts never send automatically.
- Preserve signed action time and authoritative integration time separately;
  integration time controls public ordering and moderation.
- Render the Outbox as one compact expandable row per item, with state and a
  safe summary always visible and detail/actions available on expansion.

## Shared component inventory

- **Online thread and reply cards** — reuse as the canonical visual/action
  contract for snapshot-rendered roots and comments.
- **Offline snapshot renderer** — extend its independent renderer to produce
  that contract from saved public data; it remains the offline data boundary.
- **Reaction and browser-signing flows** — extend the established identity and
  signing model for click-time offline intent creation and later validation.
- **Outbox store, delivery, and Tools page** — extend the existing local
  lifecycle and outcome surface; no second queue.
- **Service-worker/offline shell policy** — retain its public-cache boundary;
  foreground reconnect delivery does not require Background Sync.

## User flow

1. Offline, a reader sees familiar thread/comment cards and clicks Like.
2. The browser signs and queues the local intent, showing no false score change.
3. The reader scans compact Outbox rows and expands one for its timestamps,
   outcome, and recovery detail.
4. On a later foreground reconnect, queued items are processed automatically;
   accepted, conflicted, and failed outcomes remain observable.

## Success criteria

- Snapshot-rendered roots and comments meet the agreed online presentation
  contract in automated comparison coverage.
- A thread Like and a comment Like can be signed and queued offline without
  claiming server acceptance.
- A queued item advances on foreground reconnect without a Send click, while a
  draft remains unsent.
- Every delivered item retains distinguishable action and integration times.
- The Outbox scans as one row per item and exposes complete detail on demand.
