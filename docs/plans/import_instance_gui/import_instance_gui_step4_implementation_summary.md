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

## Stage 2 - Remembered sources
**Original estimate: 0.06**
**Retrospective: 0.05**
- Changes:
  - `InstanceImportSources` (`src/ForumRewrite/Import/InstanceImportSources.php`): blank-line-separated `Source:` / `Last-Used:` blocks in `sources.txt` beside the import requests, newest first, capped at 20 entries, mode 0600. It reuses `InstanceImportTask::parse` for the `Key: value` lines.
  - `InstanceImportTask::queue` remembers the source of each queued import. Resume and cancel add nothing.
- Verification:
  - `./v3 test --smoke InstanceImportTaskTest`: passed, with new test `testQueuedImportsAreRememberedNewestFirstAndCapped` (order, dedupe, cap, file format and mode, unreadable file).
  - Privacy: the file sits beside the queue database (`state/private/` by default, per `TaskQueueDatabaseConfig`). Downloads archive only the content repository, so the file never enters them.
  - Full suite not run.
- Notes: none.
- Estimate details: original scope unchanged.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*

## Stage 3 - Tools → Import page shows state
**Original estimate: 0.14**
**Retrospective: 0.16**
- Changes:
  - Flag `FORUM_INSTANCE_IMPORT_PAGE_ENABLED` ("Import page", off, site-mutable, EXPERIENCE group).
  - `ImportController` (`src/ForumRewrite/Http/ImportController.php`), modeled on `DeployController`. It renders `templates/pages/tools_import.php` and shows state only to `securityReviewAllowed()` viewers. Others see one line saying who can import.
  - `InstanceImportStatus` (`src/ForumRewrite/Import/InstanceImportStatus.php`) reads the runner's `.git/instance-import/` state: a pending run, plus the newest finished (`report.json`) or canceled (`canceled.json`) run. It is read-only.
  - `SqliteTaskQueueStore::taskProgress(taskId, limit)` returns one task's newest lines, oldest first.
  - Route `/tools/import/` behind the flag, in `isApplicationRoute`. Import sits in the Tools operator group after Deploy and is hidden from Tools while the flag is off (`TemplateRenderer` hidden-tools list).
  - Icons for `/tools/import/` (a down arrow into a tray, the inverse of Deploy) in all three sets and the shared Tools-bar list in `public/assets/theme-nav-icons.css`.
- Verification:
  - `./v3 test --smoke ImportPageTest NavIconSetsTest LocalAppSmokeTest FeatureFlagEvaluatorTest DeployPageTest`: passed after adding the flag to the evaluator's registry lists and treating Import as flag-hidden in the launcher test.
  - New tests: root sees state and the launcher entry; a viewer with only an identity hint sees no state; flag off gives a 404 and no launcher entry; status reading covers pending, canceled, broken JSON and newest-first order.
  - Rendering in each theme is deferred to Stage 6.
  - Full suite not run.
- Notes:
  - Correction: the runner's run IDs use `gmdate('YmdTHis')`, where `T` prints the timezone (`20261116UTC…`), so status parsing accepts letters between the date and time.
  - Stage 3 ran over its estimate because of the flag lists in tests.
- Estimate details: original scope unchanged; retrospective slightly higher.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*

## Stage 4 - Import, Resume and Cancel with live progress
**Original estimate: 0.14**
**Retrospective: 0.14**
- Changes:
  - `ImportController::submit` handles `POST /tools/import/` (behind the flag) with `status`, `import`, `resume` and `cancel`. It runs `SameOriginRequest::check()` and the root gate, answers JSON for `X-Requested-With: ForumImport`, and falls back to the page with a notice for a plain form. It refuses a request while one is queued or running, a new import while a run is pending, resume or cancel with nothing pending, and cancel once the content is committed (409). Bad input gets a 400.
  - `templates/pages/tools_import.php`: a source field filled with the newest remembered source and a `datalist` of the rest, plus Import. With a pending run it shows Resume and Cancel import (Resume only once committed). It also shows progress lines, the last failed request, a worker-not-running line, and a live status line.
  - `public/assets/import_control.js`, modeled on `deploy_control.js`: posts JSON, polls `status` every 3 seconds, shows progress lines, and reloads when the task finishes. A lost reply falls back to polling.
  - `tool-details.css` loads for the page, with small rules for the form row and the progress list.
- Verification:
  - `./v3 test --smoke ImportPageTest`: passed. New tests: refusals (signed out, identity hint only, non-operator, cross-site, no origin evidence), status, unknown action, resume with nothing pending, empty source, queue, remembered source, second request refused, plain-form fallback, and the page's idle, running and failed states.
  - `node --check public/assets/import_control.js`: passed.
  - Phone and desktop rendering is deferred to Stage 6.
  - Full suite not run.
- Notes: the controller-level resume and cancel paths with a real pending run are covered end to end in Stage 6, because the test fixture repository has no `.git`.
- Estimate details: original scope unchanged.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*

## Stage 5 - Readable result with details under Advanced
**Original estimate: 0.08**
**Retrospective: 0.08**
- Changes:
  - The Last import row adds the non-zero counts ("imported 1 · conflict 3"; "nothing new" when all are zero) and the image status. The command reports image status only in its output, so `ImportController::state` reads the last `Images: …` line of the finished task.
  - A Review row lists grouped review causes with their affected counts, plus exclusion reasons.
  - An Advanced section, closed by default, lists per-file conflict, invalid, unsupported and excluded entries with root causes. It stops at 500 entries and points to the saved `report.json` for the rest.
  - Small `tool-details.css` rules for the two lists.
- Verification:
  - `./v3 test --smoke ImportPageTest`: passed. New `testResultShowsCountsCausesAndFoldsPerFileDetails` uses a partial report with 10,000 conflicts. It checks counts, image status, escaped causes, exclusions, that Advanced starts closed, the 500-entry cap with the "more in" pointer, and render time under 3 seconds.
  - Full suite not run.
- Notes: correction during the stage: the first assertion counted the Review list's entry too; it now counts inside Advanced only.
- Estimate details: original scope unchanged.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*
