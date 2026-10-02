# Forte Reply Likes — Step 1: Solution Assessment

## Problem

Forte lets readers Like a top-level thread and Flag any post, but its comments and nested replies lack a Like control.

## Option A: Extend the existing post-reaction pattern to every Forte reply

- Pros: makes reply Likes post-level (matching classic); reuses the established reaction, identity, feedback, and persisted-state behavior; preserves the root thread's distinct thread-level Like.
- Cons: Forte must supply reply Like state alongside its existing reply Flag state.

## Option B: Treat a reply Like as a Like on its parent thread

- Pros: reuses the already-visible thread Like state without another per-reply state.
- Cons: a reader cannot distinguish which reply they endorsed; produces a surprising result for nested replies and does not match classic's post-level Like semantics.

## Option C: Introduce a Forte-specific reply-reaction implementation

- Pros: permits a bespoke Forte interaction design.
- Cons: duplicates established reaction, identity, error, and persistence behavior for no functional benefit.

## Recommendation

**Option A.** Add Like beside Flag to every rendered reply and use the existing post-level reaction contract and viewer state. This gives comments and replies a direct, durable Like while retaining the top-level thread Like as a thread-level action.

Reply **Approved Step 1** to proceed to the feature description.
