# Import without a worker Step 1 solution assessment

> **Feature plan:** [Step 1](./import_without_worker_step1_solution_assessment.md) · [Step 2](./import_without_worker_step2_feature_description.md) · [Step 3](./import_without_worker_step3_development_plan.md) · [Step 4](./import_without_worker_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/import_without_worker`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

Cycle estimate: 0.45

## Original Query

"the task runner is likely to be off in a new install. how else can we facilitate the import? we should also offer some existing instance choices" and, after discussing the detached worker launch: "yes, we need to make this possible for a new user on localhost, and we need to make it obvious what's happening when we press the button, that it's working, and how much time is left" (maintainer in session, 2026-11-17 UTC). Follows [`v3/import_instance_gui`](../import_instance_gui/import_instance_gui_step1_solution_assessment.md) and the screenshot behind PR #288. Feedback: PF-20261116-F02.

## Understood Intent

On a fresh install with no cron worker, including `./v3 start` on localhost, pressing Import on Tools → Import should run the import. While it runs, the page should show what it is doing, that it is still alive, and roughly how long is left. Existing instance choices are a separate request; see the end.

## Problem

Tools → Import only queues a task. With no worker, the task waits forever, and the page shows only "Queued; waiting for the task worker."

Two facts constrain the fix:
- `./v3 start` runs PHP's built-in server as a single process (`scripts/dev_server.php`). An import run inside a page request there would block every other request, including the page's own progress polling, for minutes.
- The import prints phase lines and a file count every 100 files while installing, but no overall progress, so the page cannot estimate time left from today's output.

Concepts touched: [Instance content import](../../concepts/instance_content_import.md), [Internal task queue](../../concepts/internal_task_queue.md), [Operator command progress](../../concepts/operator_command_progress.md).

## Part 1 — Getting the import to run

### Option A — The page starts a one-task worker
**Estimate: 0.12** — driver: reusing `DetachedTaskQueueLauncher` for an operator action; uncertainty: hosts that kill processes started by the web server.

When no fresh heartbeat is seen, Import queues the task and starts one detached worker (`nohup php scripts/task_queue.php run --limit=1 &`). The page then watches for the task to start, and says so plainly if it does not start within about 15 seconds. The launch does not require `FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED`, because a root operator pressed the button and the launch can only start this fixed worker command. The page cannot start a worker while the Import page flag is off.

- Works on localhost and on hosts that allow background processes, and survives closing the tab.
- The worker records progress where the page already reads it.

### Option B — Run the import in the page request
**Estimate: 0.10** — driver: the Deploy-now pattern; uncertainty: request timeouts.

- Fails on `./v3 start`, where it blocks all other requests, including progress polling. It is only useful as a last resort on multi-process hosts.

### Option C — `./v3 start` also runs the task worker
**Estimate: 0.08** — driver: the dev server keeps a worker loop alive beside PHP's server; uncertainty: low.

- Fixes localhost for every queued task (imports, rebuilds, offline snapshots), not just imports.
- Does nothing for a new hosted install without cron.

## Part 2 — Showing what is happening and the time left

### Option D — Step list, live counts and time left where measurable
**Estimate: 0.20** — driver: structured progress lines from the import plus page rendering; uncertainty: estimating the read-model refresh.

The import also prints machine-readable progress lines, for example `Progress: install 1200/7172`, alongside its current text. The page shows:
- the steps in order (starting the worker, downloading, checking, planning, installing, committing, refreshing views, images) with the current one marked;
- a ticking elapsed timer, plus the age of the last progress line, so a stall is visible;
- a time-left estimate. For download and install, it comes from bytes or files done and the current rate. For the whole import, it is a sum of measured steps plus the read-model refresh, estimated from the previous import's timing when there is one. When there is no basis, the page says "estimating" instead of inventing a number.

### Option E — Elapsed time and a spinner only
**Estimate: 0.05**

- Shows that something is running but not how long is left, which falls short of the request.

## Recommendation

Option A plus Option C, with Option D. Option C makes `./v3 start` behave like a server with cron. Option A covers new hosted installs, and the page reports truthfully when a launch fails, naming the SSH command as the fallback. I would leave out Option B because of the localhost blocking. Option D adds progress lines to the import itself, so the command line gets an overall count too.

Maintainer answers, 2026-11-17 UTC:
1. Option A may start a worker without `FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED` ("Yes, it can"). This is limited to a root operator pressing Import with the Import page flag on. The variable keeps its current meaning for read-model recovery.
2. Option C is on by default for `./v3 start`, with `--no-worker` to turn it off ("worker by default").

## Separate request: existing instance choices

A short known-instances list (zenmemes.com and qdb.us to start) in a tracked `Key: value` file, offered on the page and accepted by name on the command line. It doesn't depend on Part 1 or 2, so I'd run it as a one-shot once the earlier questions are answered: which instances to include, whether to hide an instance's own address, and whether to ship the list with the code or fetch it from zenmemes.com.

Enhancement requests: known-instances list (separate, pending answers).

## Study snapshots

- Intake (after reading the launcher, dev server and import output; no pristine intake): 0.45 for A + C + D. Baseline `34d87c86`, scope v1, R1, P2; Claude single exposed rater, 2026-11-17 UTC.
- Step 1 handoff: 0.45 reaffirmed. R1 hashes not re-verified. Option estimates are independent and not summed.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
