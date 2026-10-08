> **Feature plan:** [Step 1](./status_lock_wait_notice_step1_solution_assessment.md) · [Step 2](./status_lock_wait_notice_step2_feature_description.md) · [Step 3](./status_lock_wait_notice_step3_development_plan.md) · [Step 4](./status_lock_wait_notice_step4_implementation_summary.md)

## Stage 1 - Immediate shared-lock feedback

- Changes:
  - Reused the status command's existing shared `ExecutionLock` instance for a non-blocking pre-collection check.
  - When that lock is held, `./v3 status` now writes and flushes `Waiting for shared lock before collecting status...` before collecting the normal report.
  - Preserved the existing collector, final shared-lock status, and all unlocked output paths.
- Verification:
  - `./v3 test StatusCommandTest` — 4 passed, 0 failed.
  - Started a separate PHP process holding the command's shared lock, then ran `./v3 status` against isolated runtime paths — the waiting notice was the first output line, followed by the normal report header.
  - Deployment, migration, and external-service checks are not applicable: this is a local CLI output change.
- Notes:
  - The notice intentionally describes only the observed shared-lock state; it does not infer the holder, operation type, or duration.
