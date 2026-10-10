> **Feature plan:** [Step 1](./status_lock_wait_notice_step1_solution_assessment.md) · [Step 2](./status_lock_wait_notice_step2_feature_description.md) · [Step 3](./status_lock_wait_notice_step3_development_plan.md) · [Step 4](./status_lock_wait_notice_step4_implementation_summary.md)

## Problem

When shared protected activity can delay `./v3 status`, the command emits no output until collection finishes. Operators cannot tell whether it is waiting on the lock or has stalled.

## User Stories

- As an operator, I want immediate confirmation that `./v3 status` is waiting on the shared lock so that I can distinguish normal contention from a hung command.
- As an operator, I want the normal status report once collection completes so that I can still determine the needed next action.
- As an operator, I want an unlocked invocation to remain quiet until its existing report so that routine status output stays concise.

## Core Requirements

- Detect an already held shared lock before status collection can be delayed and immediately emit a clear waiting notice.
- Describe the observed shared-lock state without claiming the lock holder, operation type, or wait duration.
- Preserve the existing complete status report, exit behavior, and final shared-lock summary after collection.
- Keep `./v3 status` read-only; it must not create runtime state, acquire the shared lock, or change other command/API behavior.

## Delivery Scope

- Work type: application change.
- In scope: the `./v3 status` command's lock-wait feedback and focused automated coverage.
- Out of scope: lock-holder attribution, recurring progress output, persisted lifecycle state, and changes to web or other CLI status surfaces.

## Completion Boundary

- Normal entry: an operator runs `./v3 status` while shared protected activity holds the lock.
- End-to-end outcome: the command promptly identifies that it is waiting, then prints its normal status report when collection can proceed.
- Recovery: an unlocked command has no waiting notice; existing error handling remains available if collection cannot complete.
- Release condition: automated coverage proves the locked and unlocked command behaviors and existing status behavior remain intact.

## Risks

- **Lock-release race:** a lock can clear after observation, so a notice might outlive contention briefly. **Earliest validation:** controlled lock contention test. **Mitigation:** phrase the notice as an observed waiting state, not a promise of a duration or holder.
- **Output-contract regression:** scripts or tests may rely on the current output. **Earliest validation:** run existing status-command coverage. **Mitigation:** add output only for an observed held lock and retain all established report lines.
- **Misidentified delay source:** another dependency may delay collection without the shared lock. **Earliest validation:** exercise locked and unlocked collection paths. **Mitigation:** limit the new statement strictly to the shared-lock observation.

## Shared Component Inventory

- **`./v3 status` command — extend:** it is the canonical human-readable operator status renderer and owns this streaming CLI feedback.
- **Shared execution lock — reuse:** it is the authoritative existing signal for general protected activity; no competing lock or lifecycle state is needed.
- **Operator status collector — retain:** it continues to provide the final lock status and complete health snapshot; no new health definition is introduced.
- **`/api/read_model_status` and Tools → System State — unchanged:** they render related final state but are not streaming CLI surfaces, so they do not need the waiting notice.

## Simple User Flow

1. An operator runs `./v3 status` while a shared lock is held.
2. The command immediately reports that status collection is waiting on the shared lock.
3. After collection proceeds, the command prints the normal status report and next action.
4. The operator waits, retries, or follows the existing recovery guidance as indicated.

## Success Criteria

- With a held shared lock, `./v3 status` emits a clear waiting notice before its normal report.
- Without a held shared lock, the command emits no waiting notice.
- The normal status report, final lock state, and command exit behavior remain available after collection.
- The feature makes no persistent-state, lock-ownership, or non-status-surface changes.
