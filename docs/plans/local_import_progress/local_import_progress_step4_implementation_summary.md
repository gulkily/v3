> **Feature plan:** [Step 1](./local_import_progress_step1_solution_assessment.md) · [Step 2](./local_import_progress_step2_feature_description.md) · [Step 3](./local_import_progress_step3_development_plan.md) · [Step 4](./local_import_progress_step4_implementation_summary.md)

Cycle: `v3/local_import_progress`.

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_import_progress`; host ledger [local_import_progress.md](../effort_ledger/local_import_progress.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.40**

Step 3 approved 2026-11-22 ("approved step 3"). Branch `feat/local_import_progress` from `origin/main` `22fc9f1`. Planning commit `44368a4`. Step 4 entry: 0.40 reaffirmed, scope v1; stage originals copied from Step 3.

## Stage 1 - Shared step list and import requests

**Original estimate: 0.10**
**Retrospective: 0.09**

- Changes:
  - New `src/ForumRewrite/Import/InstanceImportRequests.php` holds what both import pages need: `perform(action, source, requester)` for status, start_worker, import, resume and cancel, plus `state()` and `publicState()`. The code moved out of `ImportController` unchanged.
  - `ImportController` keeps its same-origin and root-operator checks, its constructor and `START_TIMEOUT_SECONDS`, and calls the new class.
  - New `templates/partials/import_run.php` has two parts: `run` (steps, timing, log, failure line) and `not_started` (the worker notice and Try again, with an optional token field). `templates/pages/tools_import.php` renders both; its elements are unchanged.
- Verification:
  - `./v3 test --smoke ImportPageTest InstanceImportTaskTest ImportProgressViewTest`: 498 run, 498 passed (before the new test was added).
  - `./v3 test InstanceImportTaskTest`: 5 passed, with the new `testRequestsQueueForANamedRequesterAndReportTheQueuedSteps`. It queues as `local-development`, checks the recorded requester, the launched worker, the first steps and the refusals.
  - `CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/tools_import.mjs`: passed, 80 cases, none changed.
  - Full suite not run.
- Notes:
  - Both Stage 1 risks are resolved: the queue accepts a plain requester name, and Tools → Import is unchanged in tests and in the browser check.
  - `npm install` fetched `playwright-core` 1.58.1, already declared in `package.json`. No dependency was added.
- Estimate details: original scope unchanged.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
