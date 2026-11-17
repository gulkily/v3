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
