# `v3` Status Command — Step 4 Implementation Summary

## Stage 1 - Shared status snapshot

- Changes:
  - Added `OperatorStatusCollector` for read-model health, shared-lock, queue-count, and read-model rebuild-task state.
  - Routed `/api/read_model_status` and Tools → System State through the shared collector.
  - Added read-only task-store inspection and prevented lock checks from creating an absent lock file.
- Verification:
  - `./v3 test OperatorStatusCollectorTest TaskQueueStoreTest LocalAppSmokeTest::testApplicationRendersTextApisAndRss LocalAppSmokeTest::testReadModelStatusReportsLockedWhenExecutionLockIsHeld` — 12 passed.
- Notes:
  - A held lock remains general protected activity; it is not reported as proof of a manual rebuild.

## Stage 2 - `./v3 status` command

- Changes:
  - Added the read-only `./v3 status` dispatcher command with repository, read-model, queue, rebuild-task, and next-action output.
  - Added explicit repository, read-model database, and queue database path overrides plus help and unknown-option handling.
  - Added dispatcher-level tests for unavailable state, queued/running rebuilds, and the CLI error contract.
- Verification:
  - `php -l scripts/status.php`
  - `./v3 test StatusCommandTest OperatorStatusCollectorTest TaskQueueCommandTest::testTaskQueueUnknownOptionsShowUsageWithoutAPhpStackTrace` — 8 passed.
  - `./v3 status --help` printed usage; `./v3 status --unknown` printed a concise error and usage with exit code 1.
- Notes:
  - Degraded operational state is reported in normal command output; only invocation errors return a nonzero exit code.

## Stage 3 - Documentation and regression coverage

- Changes:
  - Added `./v3 status` reference documentation and updated the operator recovery runbook to make it the primary local status surface.
  - Documented the distinction between a queued-worker rebuild state and a general shared lock.
  - Added regression coverage for a missing commits capability, preserving the existing stale-status API semantics.
- Verification:
  - `./v3 test OperatorStatusCollectorTest StatusCommandTest LocalAppSmokeTest::testApplicationRendersTextApisAndRss LocalAppSmokeTest::testReadModelStatusReportsLockedWhenExecutionLockIsHeld` — 10 passed.
  - `./v3 test` — 569 passed; 6 long-standing failures reported by test history in unrelated activity/compose coverage.
- Notes:
  - The command intentionally cannot identify a manual rebuild solely from the lock; the deferred lifecycle-status work remains tracked in `todo.txt`.
