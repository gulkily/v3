# `v3` Status Command — Step 1 Solution Assessment

## Problem Statement

Operators need one `./v3 status` command that summarizes operational health, including whether a read-model rebuild is queued, running, blocked, stale, or ready.

## Option A: Aggregate existing read-model, lock, and task-queue state

Have `./v3 status` read the existing stale marker, read-model metadata, task-queue records, and shared execution-lock state.

Pros:

- Smallest implementation and no new persistent runtime state.
- Immediately reports stale/ready read-model state and queue task counts.

Cons:

- A held lock does not identify which operation holds it.
- Manual or non-queue rebuild paths cannot be reported reliably as a rebuild in progress.

## Option B: Shared lifecycle status for operational work (deferred)

Add a shared runtime status contract that rebuild paths update while active, then make `./v3 status` combine that state with read-model health and task-queue state.

Pros:

- Reports rebuild activity accurately regardless of whether it began manually or through the queue.
- Establishes an extensible operator surface for other worker commands and health signals.

Cons:

- Requires each covered operation to publish and clear lifecycle state correctly.
- Interrupted processes need a clear, conservative recovery status rather than being mistaken for active work.

## Option C: Infer activity from host processes

Have `./v3 status` inspect process lists or command lines for rebuild scripts and workers.

Pros:

- Avoids changing operation code paths.

Cons:

- Fragile across service managers, containers, permissions, and custom command invocation.
- Cannot safely distinguish unrelated processes or provide reliable lifecycle semantics.

## Recommendation

**Option A for now.** It delivers a useful, low-risk operational summary from authoritative existing state: read-model freshness, rebuild tasks queued or running in the worker, and whether the shared lock is held. The command must label a held lock as general activity rather than claim that every rebuild path is active. Defer Option B until precise lifecycle reporting across manual and queued rebuilds is needed.

Reply **Approved Step 1** to proceed to the feature description.
