> **Feature plan:** [Step 1](./status_lock_wait_notice_step1_solution_assessment.md) · [Step 2](./status_lock_wait_notice_step2_feature_description.md) · [Step 3](./status_lock_wait_notice_step3_development_plan.md) · [Step 4](./status_lock_wait_notice_step4_implementation_summary.md)

## Original Query

When I run `./v3 status` and there's a lock, I want it to report that it's waiting on a lock, not just stall for a while. Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md

## Understood Intent

Give the operator immediate, accurate progress feedback when a held shared lock can delay `./v3 status`, without changing the command's read-only status role.

## Problem Statement

`./v3 status` can block while a shared lock is held before it prints its collected status, leaving the operator unable to distinguish normal waiting from a hung command.

## Solution Options

### Option A: Print a one-time lock-wait notice before collecting status

The command non-blockingly checks the existing shared lock first and, if held, prints an immediate notice that status collection is waiting before continuing its normal collection.

- Pros:
  - Small, focused change that reuses the existing authoritative lock.
  - Preserves the current status report and read-only behavior.
  - Gives feedback before lock-related delays in downstream status reads.
- Cons:
  - The lock can be released between the check and the delayed operation.
  - Does not identify the lock holder or show ongoing progress.

### Option B: Add collection progress reporting to the status collector

Have the status-collection boundary expose lifecycle/progress events so the command can announce lock waiting and later collection phases.

- Pros:
  - Provides a reusable, accurately ordered progress contract.
  - Can grow to explain other slow status dependencies.
- Cons:
  - Broadens a narrow operator-feedback request into a collector API change.
  - Adds complexity for progress states that are not currently needed.

### Option C: Persist lock-holder lifecycle details

Require every shared-lock operation to publish its identity and lifecycle state, then show that state while `./v3 status` waits.

- Pros:
  - Could name the operation holding the lock and provide richer recovery guidance.
- Cons:
  - Requires coordinated changes across all lock users and reliable cleanup after interruption.
  - Exceeds the requested visibility improvement and introduces new runtime state.

## Recommendation

Choose **Option A**. It is a viable vertical slice: an operator invokes `./v3 status` during protected activity, immediately sees that it is waiting on the shared lock, and then receives the existing report once collection completes. The notice should describe the observed state rather than promise a specific lock holder or duration. Defer richer progress or lock-holder attribution until there is a separate operational need for it.
