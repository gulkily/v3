# Offline Participation UI Refinement Step 1 Solution Assessment

## Problem

Offline JavaScript snapshot views should match the online PHP-rendered forum
where supported—including thread and comment Likes—while queued actions send
automatically after connectivity returns and the Outbox remains compact and
understandable. Queued work also needs to preserve its signed action time
separately from the server's integration time without trusting device clocks as
public ordering or moderation authority.

## Timestamp policy

- Preserve a signed **action time**: when the person created/liked the item.
- Record an authoritative **integration time**: when the server validated and
  merged it.
- Create the detached signature at the offline action itself. A queued Like
  signs its stable intent ID, target, tag, and action time immediately; queued
  replies and threads likewise sign their frozen action payload before waiting
  for delivery.
- Use integration time for public ordering, score/moderation effects, and
  operational history; display action time as attributed context, not as a
  claim about server receipt.
- Treat implausible or missing device time as client-asserted metadata and
  retain the server time rather than rejecting or silently rewriting history.

## Option A — Keep separate offline controls and manual delivery

- Pros:
  - Preserves the current explicit-send safety boundary.
  - Requires the smallest change.
- Cons:
  - Offline Like controls and content presentation visibly diverge from online.
  - A multi-line Outbox is unnecessarily dense.
  - Reconnection leaves queued work waiting for a manual visit.

## Option B — Reuse online presentation with foreground automatic queue delivery

- Pros:
  - Makes the independent JavaScript snapshot renderer follow the online
    title/body and reaction presentation contract, instead of inventing an
    offline-specific appearance.
  - Supports the same eligible Like affordance for a thread and each visible
    comment; offline activation queues an intent rather than changing a score.
  - Makes each Outbox item a compact, expandable row.
  - Sends only deliberately queued items when the browser regains connectivity
    or opens the cached Outbox online; drafts remain local.
  - Keeps delivery observable through item state and outcome after each attempt.
  - Makes the two timestamp meanings visible without changing public ordering.
- Cons:
  - Changes the prior no-automatic-send policy for queued replies and threads.
  - Needs a durable single-delivery lock, retry rules, and clear handling for
    browser restarts and failed reconnects.

## Option C — Background Sync for automatic delivery

- Pros:
  - Can deliver without an open page on supporting browsers.
- Cons:
  - Inconsistent browser support and harder lifecycle/permission behavior.
  - Makes queued composition less visible at the moment it is sent.
  - Adds operational complexity before the foreground queue path is proven.

## Recommendation

Choose **Option B**. Make supported offline content and Like controls share the
online presentation contracts despite their separate renderers, including
visible comment Likes. Compact the Outbox into expandable rows and
automatically process only items the person has explicitly queued when a
foreground page detects connectivity. Keep drafts unsent, retain every outcome,
and defer Background Sync to a separate FDP slice.
