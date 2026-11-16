# Import-instance GUI Step 4 implementation summary

> **Feature plan:** [Step 1](./import_instance_gui_step1_solution_assessment.md) · [Step 2](./import_instance_gui_step2_feature_description.md) · [Step 3](./import_instance_gui_step3_development_plan.md) · [Step 4](./import_instance_gui_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/import_instance_gui`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.65**

Step 3 approved 2026-11-16 ("approved step 3"). Branch `feat/import_instance_gui` from `origin/main` at the merge of PR #285 (cancel command). Planning commit `e2249ac3`.

Step 4 entry: 0.65 whole-cycle projection reaffirmed, scope v3 unchanged; stage-work originals copied from Step 3 (not new ratings). Claude single exposed rater, 2026-11-16 UTC.

## Stage 1 - Worker runs a queued import
**Original estimate: 0.18**
**Retrospective: 0.18**
- Changes:
  - `InstanceImportTask` (`src/ForumRewrite/Import/InstanceImportTask.php`): `queue()` writes a private `Key: value` request file (Action, Source, Requested-By, Requested-At, mode 0600) named by the task's deduplication key, then enqueues one `instance_import` task. It refuses while one is queued or running. `run()` starts `scripts/import_instance.php` through `BoundedProcess` with a 330-second deadline. It passes each output line to a progress callback and keeps the full output in a private log beside the request. Destination paths come from the worker.
  - `BoundedProcess::run` takes an optional output callback; its two existing callers are unchanged.
  - `SqliteTaskQueueStore::INSTANCE_IMPORT`; `TaskQueueWorker` gains `runInstanceImport`. Exit codes 0 and 2 complete the task, and anything else fails it with the last output line. Imports never retry.
  - `scripts/task_queue.php` wires the handler, recording each line with `recordTaskProgress`.
- Verification:
  - `./v3 test --smoke InstanceImportTaskTest TaskQueueWorkerTest BoundedProcessTest`: passed (smoke set included). New tests: loopback import through the worker with progress and log, refused second request, failed source, rejected inputs, and path-traversal keys.
  - The import's 300-second limit plus 330-second handler deadline sit under the queue's 900-second abandoned-task recovery.
  - Full suite not run.
- Notes:
  - Requests and logs live in `instance_import/` beside the queue database, under private state by default.
  - The new test is registered in `tests/run.php`.
- Estimate details: original scope unchanged; no revision.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*
