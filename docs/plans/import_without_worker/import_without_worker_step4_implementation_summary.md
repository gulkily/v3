# Import without a worker Step 4 implementation summary

> **Feature plan:** [Step 1](./import_without_worker_step1_solution_assessment.md) · [Step 2](./import_without_worker_step2_feature_description.md) · [Step 3](./import_without_worker_step3_development_plan.md) · [Step 4](./import_without_worker_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/import_without_worker`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.46**

Step 3 approved 2026-11-17 ("approved step 3"). Branch `feat/import_without_worker` from `origin/main` `936c6357`. Planning commit `068409b4`. Step 4 entry: 0.46 reaffirmed, scope v2; stage originals copied from Step 3.

## Stage 1 - Structured progress from the import
**Original estimate: 0.07**
**Retrospective: 0.06**
- Changes:
  - The import now prints `Progress: <step>` lines, with `<done>/<total>` where there is a count:
    - `download 0/?` at the start, then bytes against the Content-Length total every MiB (`?` without a length), then `bytes/bytes` at the end;
    - `extract` and `plan` as single lines;
    - `install 0/N`, every 100 files and `N/N`;
    - `commit`, `publish`, `images` and `done`.
  - The lines are printed from `scripts/import_instance.php`, `InstanceArchiveDownloader` and `ContentImportRunner`. The existing text lines are unchanged.
- Verification: `./v3 test --smoke ImportInstanceCommandTest ContentImportRecoveryTest InstanceImportTaskTest`: 491 passed (smoke set included). `testRealInstanceDownloadPreviewImportAndRepeat` now checks the step order and that install and download start at zero and end complete. Full suite not run.
- Notes: Step 3 said each step prints a start and an end line. Steps without counts print only a start line; the next step's start marks the end, and `done` closes the run. A resume skips download, extract and plan.
- Estimate details: original scope unchanged.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Stage 2 - `./v3 start` runs the task worker
**Original estimate: 0.08**
**Retrospective: 0.12**
- Changes:
  - `DevServerWorker` (`src/ForumRewrite/Support/DevServerWorker.php`) starts `setsid php scripts/task_queue.php run --quiet --limit=1` when none is running and at least 10 seconds have passed since the last start. `scripts/dev_server.php` calls it each time round its loop and prints "task worker on (./v3 start --no-worker turns it off)". `v3 start --no-worker` passes the flag through, and the usage text is updated.
  - On stop, the server waits up to 2 seconds. A worker still busy after that is left to finish, with "task worker still finishing a task; it exits on its own" in the output.
  - `README.md` and `docs/reference/v3_cli.md` describe the worker and `--no-worker`.
  - `tests/run.php` points `FORUM_TASK_QUEUE_DATABASE_PATH` at a per-run temporary queue unless it is already set. The two `LocalAppSmokeTest` stale-head tests use their own empty queue.
- Verification:
  - `./v3 test --smoke DevServerWorkerTest`: 471 passed (smoke set included).
  - New tests: repeated starts with the interval and the queued task claimed; the dev server with and without `--no-worker` (heartbeat fresh, or not, and no idle worker left after stop).
  - The earlier failing `LocalAppSmokeTest`, `ActivityProjectionTest` and `BoardSearchTest` cases pass with the isolation. Full suite not run.
- Notes:
  - Deviation from Step 3: the plan said to terminate the worker on shutdown. A busy worker may be running an import, and the import runs in its own session (`BoundedProcess`/`InvocationSupervisor` use setsid). Killing the worker would orphan the import and leave its task "running" until the queue's abandoned-task recovery, so a busy worker is left to finish. An idle worker exits within a second and is waited for.
  - Pre-existing test problem found and fixed: smoke tests wrote to the checkout's own queue (`state/private/internal_tasks.sqlite3`). A fresh heartbeat or queued rebuild left there made 24 smoke tests in this workspace fail with "rebuild updating". A running `./v3 start` worker would cause the same, so the runner now isolates the queue.
- Estimate details: retrospective higher for the test isolation work.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Stage 3 - Import starts a worker and says when it doesn't start
**Original estimate: 0.10**
**Retrospective: 0.11**
- Changes:
  - `DetachedTaskQueueLauncher::launchForOperator()` uses the same fixed one-task command without the `FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED` check. `launchQueuedWorker()` still requires it. Both now name the PHP binary through `PhpCli::binary()`. The old `PHP_BINARY` is php-fpm under a web server, so the emergency launch could never have started a CLI worker there.
  - `ImportController`:
    - After queuing Import, Resume or Cancel with no fresh heartbeat, it starts one worker through an injected launcher (wired in `Application::importController`). It records `Worker-Launch` and `Worker-Launch-At` on the request (`InstanceImportTask::annotate`).
    - A new `start_worker` action ("Try again") relaunches only when the latest import is still queued.
    - `state()` reports `start`: `waiting` (fresh worker), `starting` (launched less than 15 seconds ago), `not_started` (launch failed or unavailable, or not claimed within 15 seconds) or `started`. It also reports `worker_command`.
  - Page: when `start` is `not_started`, it shows "The task worker didn't start.", the command `php scripts/task_queue.php run --limit=1` and a Try again button. The status line says "Starting a worker…" or "Queued; the task worker picks it up shortly." The old idle line about the worker not having run is removed, since Import now starts one. `import_control.js` follows these states and handles Try again.
- Verification:
  - `./v3 test --smoke ImportPageTest DetachedTaskQueueLauncherTest InstanceImportTaskTest`: passed (smoke set included).
  - New `testStartStateFollowsLaunchHeartbeatAndTimeout` uses a fixed clock and covers not started, starting, timed out, failed launch, fresh worker and claimed.
  - The actions test checks that Import with no worker launches and records it, and that Try again works. It runs with `FORUM_PHP_CLI=/bin/false`, so tests never start real workers.
  - Refusal tests still show that non-root, cross-site and flag-off requests reach no launch. The emergency path's tests are unchanged and pass.
  - Full suite not run.
- Notes: the plan's "the page shows the SSH command for this import" became the worker command. It runs the queued request as is, so the operator doesn't retype the source or destination.
- Estimate details: original scope unchanged.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Stage 4 - Steps, liveness and time left
**Original estimate: 0.15**
**Retrospective: 0.17**
- Changes:
  - `ImportProgressView` (`src/ForumRewrite/Import/ImportProgressView.php`) turns a task's `Progress:` lines and their event times into:
    - the ordered steps with done, current or pending status and counts;
    - the run's start time and last-update time;
    - `step_seconds_left`, from the current step's rate after 2 seconds, or else from the previous run's duration for that step;
    - `seconds_left` for the whole import, given only when a previous run's step times exist.
  - A resumed run omits the steps it skipped, and a finished run marks every step it ran as done.
  - `InstanceImportTask::run` records each step's start time. After a finished full import it writes `Step-Seconds: download=… extract=…` on the request. `previousStepSeconds()` reads the newest such line from another request.
  - `ImportController::state()` reads up to 2,000 events for the latest task. It keeps the last 40 non-`Progress:` lines as the raw log and builds `steps` with a worker step first ("Starting the task worker", "Waiting for the task worker", then "Task worker started"). It also builds `timing` (elapsed, seconds since update, step and whole-import seconds left). The status poll returns `steps` and `timing`.
  - Page:
    - an ordered step list (✓ done, ▸ current in bold, ○ pending) with counts ("1,200 of 7,172", "47 KB of 47 KB");
    - "Elapsed m:ss · updated N s ago · about N min left", or "under a minute left", "… in this step" or "estimating time left", rendered on the server so the first paint is final;
    - the raw lines folded under Log;
    - the form hidden while an import runs;
    - the Task worker row and the "Importing…" status line removed, since the steps show both.
  - `import_control.js` redraws the steps and timing on each 2-second poll and ticks the timer every second between polls.
- Verification:
  - `./v3 test --smoke ImportProgressViewTest InstanceImportTaskTest ImportPageTest`: passed (smoke set included). New `ImportProgressViewTest` covers counts and status, the rate estimate (1,200 files in 5 s gives 25 s left), no estimate without a basis (unknown total, nothing done yet, no count and no previous run), previous-run figures with no negative time, resumed and finished runs, and duration round trips. `InstanceImportTaskTest` checks `Step-Seconds` after a loopback import. The page test checks the running markup.
  - `CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/tools_import.mjs`: passed, 80 cases. The running state checks a visible current step, a hidden form and the timer changing within 1.1 s. The follow-through checks "Starting the task worker", then "Installing records 1,200 of 7,172" with "about 3 min left in this step". Screenshots reviewed in gloss 390, word97 1280 and bubblegum 390.
  - Full suite not run.
- Notes: corrections during the stage: small downloads showed "0.0 of 0.0 MB" (now KB under 1 MB), and the hidden form stayed visible because `.deploy-actions` sets `display` (now `.import-actions[hidden]`).
- Estimate details: retrospective higher for the browser check updates.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Stage 5 - End-to-end on `./v3 start` and docs
**Original estimate: 0.06**
**Retrospective: 0.09**
- Changes:
  - Fixes from the real runs:
    - The planner now prints `Progress: plan <done>/<total>` every 250 files. The total counts two passes over local records plus one over the source's. Before this, planning showed no update for about 23 seconds on a 7,000-record instance.
    - `Progress: plan` is printed before the local scan, which had been showing under "Checking the archive".
    - A download at 0 bytes with no known size shows no count, instead of "1 KB".
    - The dev-server worker interval is 5 seconds, down from 10, so "Waiting for the task worker" lasts at most about 5 seconds.
    - Elapsed time counts from the request while the task waits.
    - In the Review row, exclusion root causes are no longer listed a second time beside `exclusion_reasons`.
  - Docs:
    - The runbook's Browser entry section now covers running without cron, the page-started worker, the not-started recovery and the steps and time left.
    - `docs/concepts/internal_task_queue.md` covers workers without cron and the test queue isolation.
    - The CLI reference covers the 5-second worker interval.
    - Entries added to the UI copy checklist and the feedback item `PF-20261116-F02`.
- Verification:
  - A fresh git worktree of this branch, `./v3 start 127.0.0.1:8123` with the Import page flag on, a root session, and Chromium importing zenmemes.com from Tools → Import:
    - First import: "Waiting for the task worker" (about 9 s with the old 10-second interval), then download, check, planning, install (300 of 7,288), views and images. Finished in about 40 s with "needs review": imported 7,288 · duplicate 1 · conflict 4 · invalid 193 · unsupported 81 · excluded 716 · images complete. `Step-Seconds` was recorded.
    - Repeat imports used that timing ("under a minute left"). With the planner counts, planning updated every 1–7 seconds.
    - `--no-worker` with a stale heartbeat: the page showed "Starting the task worker", its launched worker ran the import to completion (`Worker-Launch: launched`), and no worker process was left.
    - `FORUM_PHP_CLI=/bin/false` with `--no-worker`: after 15 seconds the page showed "The task worker didn't start.", the command and Try again.
  - `./v3 test --smoke ImportPageTest ImportInstanceCommandTest ContentImportRecoveryTest InstanceImportTaskTest DevServerWorkerTest ImportProgressViewTest`: 505 passed.
  - `./v3 test --smoke` with every test file that uses `ContentImportPlanner` or `ContentImportRunner` (including `WriteApiSmokeTest` and `ContentImportPlannerTest`): 763 passed.
  - `node tests/browser/tools_import.mjs`: 80 cases passed. `node tests/browser/tools_launcher.mjs`: 38 cases passed.
  - `./v3 feedback check`: no problems. `git diff --check`: clean.
  - Full suite not run.
- Not verified:
  - A hosted install without cron (php-fpm). The launch now uses `PhpCli::binary()`, but a real php-fpm host wasn't tried. Shared hosts that kill web-started processes fall back to the not-started message.
  - On a fresh `./v3 start`, the first worker run before any page request logs "unable to open database file" to `state/private/task_queue_errors.log`, because the read model doesn't exist yet. Later runs succeed. This was left as is; the cron worker behaves the same.
  - Browsers other than Chromium.
- Final review: the diff from the planning commit touches only files named in Stages 1–5. It reuses the existing launcher, queue store, `BoundedProcess` and progress callbacks. No dependency was added.

## Study closeout

- Retrospective scope assessment (not a measured actual): 0.55 for scope v2 as delivered, against the 0.46 projection. The extra work: the test-queue isolation found in Stage 2, and the planner progress and fixes found in the real end-to-end runs.
- Maintainer comfortable-fit judgment: pending. Acceptance: pending.
- Claude single exposed rater, R1, P2, 2026-11-17 UTC.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
