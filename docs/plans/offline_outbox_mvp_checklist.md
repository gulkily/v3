# Offline Participation MVP Checklist

**Status:** MVP implementation tracking. Checked items are delivered by the
initial Outbox release; remaining items are deliberate follow-up work.

## MVP scope

- [x] Deliver these four features: a shared Outbox, supported offline voting,
  offline replies, and offline new-thread compose.
- [x] Show every queued intent from the other three features in the Outbox.
- [x] Keep flags, tagging, deletion, moderation, account actions, invitations,
  attachments, and privileged actions out of this MVP.
- [x] Treat an offline item as local until the server confirms acceptance.
- [x] Do not silently submit queued replies or threads after reconnecting.

## Feature 1 — Outbox

- [x] Provide a normal UI entry point with a pending-item count.
- [x] List all items across supported action types, newest first by default.
- [x] Show action type, safe target description, local creation time, last
  attempt time, and current state.
- [x] Support the MVP states: `draft`, `queued`, `waiting for connection`,
  `sending`, `accepted`, `rejected`, `conflicted`, `cancelled`, and `needs
  attention`.
- [x] Provide accessible counts and a plain-language explanation for each
  non-ready state.
- [ ] Allow only state-appropriate actions: open/edit draft, queue, send/retry,
  cancel, discard, and export.
- [x] Retain accepted/rejected outcomes long enough to be understandable, with
  a clear remove-history action.

## Feature 2 — Offline voting

- [x] Define exactly which existing reaction(s) are eligible for queueing.
- [x] Queue an intent instead of presenting an offline change as server truth.
- [ ] Make repeat/undo behavior unambiguous before the original intent is sent.
- [ ] Reconcile target removal, hidden content, authorization changes, and
  duplicate server acceptance.

## Feature 3 — Offline replies

- [ ] Store reply content as an editable local draft before it is queued.
- [ ] Preserve target-thread context and explain when it is absent, locked,
  hidden, or removed.
- [x] Require an explicit online send/retry path that completes the existing
  prepare, signing, and finalization lifecycle.
- [x] Map an accepted local intent to its canonical server post and link it from
  the Outbox.
- [x] Keep queued content visibly distinct from published content in every UI.

## Feature 4 — Offline new-thread compose

- [ ] Store subject and body as an editable local draft before it is queued.
- [ ] Restore, edit, export, discard, and queue a new-thread draft after a
  browser restart.
- [x] Require an explicit online send/retry path that completes the existing
  prepare, signing, and finalization lifecycle.
- [x] Map an accepted local intent to its canonical thread/post and link it from
  the Outbox.
- [x] Keep queued new threads visibly distinct from published threads in every
  UI.

## Shared trust, privacy, and data lifecycle

- [x] Keep pending work outside the public offline-reader snapshot/cache.
- [ ] Define device/shared-browser retention, clear-data, export, and discard
  behavior before persisting any compose content.
- [x] Avoid storing private keys or exposing authenticated data through the
  public cache.
- [ ] Use stable local intent identifiers and require server-side idempotency
  before retries are possible.
- [x] Make browser storage failure, quota pressure, and unavailable storage
  visible before a person loses work.

## Shared connection and reconciliation

- [x] Work without Background Sync; treat it only as an optional accelerator.
- [ ] Detect offline/online changes without claiming that reconnection means
  submission succeeded.
- [x] Recheck authorization, target availability, and validation at send time.
- [x] Record safe failure/conflict explanations and leave recoverable items in
  the Outbox.
- [x] Ensure cancelling or clearing data cannot accidentally send an item.

## Verification and release gates

- [x] Unit-test state transitions, ordering, counts, and allowed controls.
- [ ] Test each action through offline creation, restart, reconnect, accepted,
  rejected, conflicted, and cancelled outcomes.
- [ ] Test duplicate retries and server idempotency.
- [ ] Test storage clearing, quota/storage failure, unavailable signing, and
  missing/locked/hidden reply targets.
- [ ] Run browser QA with and without Background Sync on supported browsers.
- [x] Publish a concise user guide describing drafts, queued items, outcomes,
  privacy, and recovery.

## MVP acceptance demo

- [ ] Offline: queue one supported vote, one reply, and one new thread; inspect
  all three in the Outbox without a network connection.
- [ ] Restart: confirm their state and editable content survive as documented.
- [ ] Reconnect: explicitly send each item and observe accepted, rejected, or
  needs-attention results without duplicate server actions.
- [ ] Recover: cancel/export/discard an item and clear offline data only after
  an explicit warning about pending work.
