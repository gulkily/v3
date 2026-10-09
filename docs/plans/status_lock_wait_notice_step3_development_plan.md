> **Feature plan:** [Step 1](./status_lock_wait_notice_step1_solution_assessment.md) · [Step 2](./status_lock_wait_notice_step2_feature_description.md) · [Step 3](./status_lock_wait_notice_step3_development_plan.md) · [Step 4](./status_lock_wait_notice_step4_implementation_summary.md)

## Completion Contract

- Normal entry: an operator runs `./v3 status` while the existing shared execution lock is held.
- End-to-end outcome: the command immediately emits a shared-lock waiting notice, then prints its unchanged complete report after status collection proceeds.
- Required recovery: an unlocked invocation emits no notice; existing command error handling remains the recovery path if collection fails.
- Deployment/external verification: not applicable; this is a local CLI behavior with no deployment, migration, or external-service change.
- Release condition: focused locked/unlocked command coverage and the existing status-command suite pass.

## Key Risks

- **High risk: usability — stale lock observation can briefly produce an unnecessary notice.** Early validation: controlled lock contention. Mitigation: word the notice as an observed state and do not identify a holder or promise a wait duration.
- **High risk: usability — buffered output could still appear as a stall.** Early validation: observe output from a live contended command. Mitigation: ensure the notice is emitted and flushed before collection.
- **Output compatibility risk:** routine consumers may expect existing unlocked output. Early validation: unlocked command coverage. Mitigation: add output only for the held-lock path and retain all report lines.

## Stage 1

- Goal: Give a contended `./v3 status` invocation immediate, truthful feedback.
- Dependencies: Approved Step 2; existing shared execution-lock status contract.
- Expected changes: Extend the status command to non-blockingly inspect the existing shared lock before collection; when held, write and flush one waiting notice, then continue through the current status-collection and rendering path unchanged.
- Verification approach: Run the focused status-command tests and manually exercise a held-lock invocation to confirm the notice precedes the final report.
- Risks or open questions:
  - Impact: a lock may release after the non-blocking inspection.
  - Early warning / validation: test an invocation where contention is released after the notice appears.
  - Mitigation: state only that the shared lock was observed and that status collection is waiting.
- Canonical components/API contracts touched: `scripts/status.php`; `ExecutionLock::isLocked()` as the sole lock signal; `OperatorStatusCollector` and its final lock-state report remain unchanged.

## Stage 2

- Goal: Lock in the prompt locked/unlocked CLI behavior without weakening the existing status contract.
- Dependencies: Stage 1 completed and manually validated.
- Expected changes: Add focused command-level coverage that holds the shared lock in a separate process, observes the waiting notice before status collection completes, releases contention, and asserts the unchanged final report; cover the absence of the notice when unlocked.
- Verification approach: Run the focused status-command test class, then the full project test suite if its normal runtime is available.
- Risks or open questions:
  - Impact: a completion-only test could pass without proving prompt output.
  - Early warning / validation: read live command output while the holder remains active.
  - Mitigation: make the test wait for the notice before releasing the separate lock holder.
- Canonical components/API contracts touched: `tests/StatusCommandTest.php`; the `./v3 status` CLI output contract.
