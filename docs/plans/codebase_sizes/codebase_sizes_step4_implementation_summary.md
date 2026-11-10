# codebase_sizes — Step 4: Implementation Summary

> **Feature plan:** [Step 3](./codebase_sizes_step3_development_plan.md) · [Step 4](./codebase_sizes_step4_implementation_summary.md)

- Debug mode: off (not enabled for this cycle).
- Effort study: Step 3 was written without a projected-total snapshot; Step 4 entry projection unavailable, not backfilled. Retrospective and maintainer comfortable-fit judgment: pending.
- Open question from Step 3 (download sizes inline): approved as proposed (yes).

## Stage 1 - Byte formatting and size probes

- Changes:
  - New `ForumRewrite\Support\ByteSize`: `format(int): string` (binary units, one decimal) plus the probes `file()`, `gitStore()`, `trackedTree()`. They sit here, not as private controller methods as planned, so they can be tested without rendering the page.
  - `CodebaseStateController::collectState()` gains `sizes` (read-model database, committed content records, content repository Git store, app checkout Git store), each with a relative `state/...` source command.
  - `tests/ByteSizeTest.php`, registered in `tests/run.php`.
- Verification:
  - `./v3 test ByteSizeTest LocalAppSmokeTest`: 168 run, 168 passed.
  - Probe timing on the v3 checkout itself (4,016 tracked files, 46 MiB): `trackedTree` 18 ms, `gitStore` 4 ms.
- Notes:
  - First draft ran git through `BoundedProcess`, which reads 64 KiB per 10 ms poll: 665 ms for the same `ls-tree`. A large content repo would hit the budget and show "unavailable". Switched to coreutils `timeout -s KILL 2` with a blocking read and a 64 MiB output cap; `BoundedProcess` left unchanged.
  - With no `timeout` binary or no `proc_open`, probes return null and the row reads "unavailable".

*— Claude (Opus 5.5) · 2026-11-10 UTC*
