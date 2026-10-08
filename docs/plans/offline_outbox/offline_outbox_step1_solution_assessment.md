# Offline Outbox Step 1 Solution Assessment

## Problem statement

Votes, replies, and new threads need one observable, trustworthy way to keep
offline intent until the server has explicitly accepted or rejected it.

## Option A — Separate queue for each feature

**Pros**

- Fastest path for a single vote/reaction type.
- Each screen can optimize its own local behavior.

**Cons**

- Fragments pending work across the UI.
- Duplicates state, retry, privacy, and reconciliation rules before replies and
  threads arrive.

## Option B — Shared Outbox with action adapters

**Pros**

- One observable queue, state vocabulary, recovery path, and pending count.
- Lets votes prove idempotency/reconciliation before replies and threads reuse
  the same contract.
- Keeps draft, queued, and accepted states visibly distinct.

**Cons**

- Requires a small shared foundation before the first action type ships.
- Needs careful scope discipline to avoid becoming a general sync engine.

## Option C — Automatic background submission first

**Pros**

- Lowest apparent effort after a connection returns.
- Can use browser background features where available.

**Cons**

- Risks surprising submission, unavailable signing, and unclear failures.
- Browser support is inconsistent; hidden retries undermine observability.

## Recommendation

Choose **Option B**: build a shared, user-visible Outbox first; add one
supported vote/reaction as the proving action; then add replies and new
threads. Submission of composed content remains explicit when online, while
Background Sync may later accelerate checks without hiding state or outcomes.
