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

## Stage 2 - The local page queues the import and shows the steps

**Original estimate: 0.10**
**Retrospective: 0.14**

- Changes:
  - `LocalDevelopmentPageController` no longer runs the import in the request. After `LocalDevelopmentImport::authorize()` it calls `InstanceImportRequests::perform()` as requester `local-development`, then redirects to `/local/`. A refusal renders the page with the reason and its status code.
  - `templates/pages/local_development.php` renders the `run` part of `import_run.php`. The form, with its explanation inside it, is hidden while an import runs. "Importing… Keep this page open." and `public/assets/local_development.js` are removed.
  - `import_run.php` adds a `noscript` reload every 5 seconds while an import runs, on both import pages.
  - `TemplateRenderer` gives the local page `tool-details.css`, where the step list styles already live.
  - `InstanceImportRequests::latestLog()` returns the newest request's output for Import details.
  - `Application.php` builds the local page's `InstanceImportRequests`; Tools → Import's wiring is unchanged.
- Verification:
  - `./v3 test --smoke LocalDevelopmentImportTest InstanceImportTaskTest ImportPageTest`: 496 run, 496 passed.
  - `LocalDevelopmentImportTest`, rewritten for the queue:
    - Import answers 303 in under a second, and the page then shows the steps.
    - Failed download then Retry, blocked publication then Resume, repeat import without duplicates, and a conflict keeping the local record all pass, with a worker the page itself started.
    - A new import during an interrupted one gets 409.
    - With no worker able to run: the first step is current, the form is hidden, the `noscript` reload is present, a thread page answers in under 2 seconds and a reload shows the same step.
    - Refusals: a wrong token, a foreign Origin and a cross-site fetch get 403 for import, resume, status and start_worker. Proxied and wrong-Host posts never reach the local page (405 from ordinary routing). `cancel` gets 400.
  - `node tests/browser/tools_import.mjs`: passed, 80 cases unchanged.
  - Pending: the scripts-off browser check moves to Stage 3, where `tests/browser/local_development.py` is reworked. Full suite not run.
- Notes:
  - **Defect found and fixed (in scope, shared code):** a second import with no recurring worker never started. The worker the page started for the first import left a fresh heartbeat, and the page read that as "a worker is coming". Imports now start a worker unless one is running right now. The queue's lock makes an extra worker harmless, and under `./v3 start` the import begins without waiting for the next 5-second round. This also applies to Tools → Import. `testRequestsQueueForANamedRequesterAndReportTheQueuedSteps` covers it.
  - Deviation: the handler already answers `status` and `start_worker`, with JSON for the page script, because one handler serves every action. The script itself is Stage 3.
  - Known limit: if a one-task worker is busy with another task when Import is pressed, no worker is started, and that worker exits after its task. The import then waits for the next recurring worker. Not reachable under `./v3 start`, which starts a worker every 5 seconds.
  - Tools → Import's failure line shows the command's last line ("Elapsed time: 0.02 seconds"), not the reason. Stage 4 gives the local page the reason; Tools → Import is left as is and noted for follow-up.
- Estimate details: retrospective higher for the fresh-heartbeat defect and the test rewrite.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

## Stage 3 - Live steps and a worker that doesn't start

**Original estimate: 0.08**
**Retrospective: 0.08**

- Changes:
  - `public/assets/import_control.js` posts to the form's own address and sends the form's token when it has one. Tools → Import is the default and is unchanged.
  - The local page loads that script and renders the `not_started` part of `import_run.php` (the notice, the worker command and Try again, with the token) plus the status line the script writes to.
  - `InstanceImportRequests::steps()` keeps the pending steps listed between the worker's claim and the import's first line. Before, the list shrank to one line for a second or two.
- Verification:
  - `./v3 test LocalDevelopmentImportTest ImportPageTest InstanceImportTaskTest`: 15 run, 15 passed. New assertions: `status` returns JSON with steps, timing and the worker command; `start_worker` answers for a queued import; a wrong token gets 403 as JSON; the page carries the Try again form.
  - Real imports of https://zenmemes.com from the local start page in Chromium, each on a fresh copy under `./v3 start`:
    - Default worker: first step visible 0.33 s after the click; longest gap without a visible change 1.9 s; `/threads/?view=all` answered in 75 ms mid-import; finished in 32 s; no page errors. Steps seen: waiting for the worker, checking, planning with counts, installing 8,813 of 8,813, copying images.
    - `--no-worker`: "Starting the task worker", then the same steps; finished in 37 s; no worker process left behind.
    - A second import straight after, still `--no-worker`: started in 2.4 s and showed "under a minute left" for the whole import, from the first run's timings. This is the case that hung before the Stage 2 fix.
    - `--no-worker` with `FORUM_PHP_CLI=/bin/false`: "The task worker didn't start.", the command and Try again appeared 16.3 s after the click.
  - JavaScript off, real import: the page reloaded itself every 5 seconds, the current step advanced through planning, installing and images, and it ended on the result.
  - Screenshots reviewed: Gloss 390, Word97 1280 and Bubblegum 390 in the running state; no sideways overflow in any.
  - Full suite not run.
- Notes:
  - The timer ticks once a second but is not aligned with the 2-second poll, so the visible text can stand still for up to about 2 seconds. Step 2's limit is 10 seconds.
  - Found in the Bubblegum screenshot and fixed in Stage 4: when the script starts a second import, the previous result stayed above the steps.
- Estimate details: original scope unchanged.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
