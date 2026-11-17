# Import without a worker Step 2 feature description

> **Feature plan:** [Step 1](./import_without_worker_step1_solution_assessment.md) · [Step 2](./import_without_worker_step2_feature_description.md) · [Step 3](./import_without_worker_step3_development_plan.md) · [Step 4](./import_without_worker_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/import_without_worker`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.45**

## Approved direction

Step 1 approved 2026-11-17 ("approved step 1"): Options A, C and D. Maintainer answers:
- Import may start a worker without `FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED`.
- `./v3 start` runs the worker by default, with `--no-worker` to turn it off.

The known-instances list stays a separate request.

## Problem

On a fresh install, including `./v3 start` on localhost, Tools → Import queues a task that never runs. While an import does run, the page shows raw output lines but not which step it is on, whether it is still alive, or how long is left.

Concepts touched: [Instance content import](../../concepts/instance_content_import.md) (new structured progress lines), [Internal task queue](../../concepts/internal_task_queue.md) (operator-started worker; dev-server worker), [Operator command progress](../../concepts/operator_command_progress.md) (step, count and time-left reporting). Proposed inventory addition: none.

## User stories

- As a new operator on localhost, I want Import to just run so that I don't have to learn about cron first.
- As an operator of a new hosted install without cron, I want Import to start the work itself so that I don't need SSH.
- As an operator, I want to see the current step, that it is still moving, and the time left so that I know whether to wait or worry.
- As an operator, I want a plain message when the work can't start so that I know what to do instead.

## Core requirements

- `./v3 start` keeps a task worker running beside the local server unless `--no-worker` is given. The worker stops when the server stops. Its startup line says the worker is on.
- When no fresh worker heartbeat is seen, Import, Resume and Cancel queue the task and start one detached worker. Only a root operator's request can do this, only while the Import page flag is on, and only the fixed worker command can be started. `FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED` keeps its meaning for read-model recovery.
- If the task has not started about 15 seconds after the launch, the page says the worker could not be started and shows the SSH command for this import.
- The import prints structured progress lines (`Progress: <step> <done>/<total>`) as well as its current text. The command line shows them too.
- The page shows the steps in order with the current one marked, a ticking elapsed timer, and the time since the last progress line. While downloading and installing, it shows a time-left estimate based on the measured rate. A whole-import estimate uses the previous import's per-step timing when there is one; otherwise the page says "estimating".

## Delivery scope

Work type: application change; the dev-server change is part of the local runtime. The Import page flag still gates the page; no new flag.

## Completion boundary

- Entry: Tools → Import on `./v3 start` with no cron; also a hosted install without cron.
- Outcome: pressing Import runs the import while the page shows steps, elapsed time and time left, then the result.
- Recovery: a launch that fails or doesn't start shows the SSH command. A worker killed mid-import leaves a pending run with Resume and Cancel, as today. A stall shows as a growing "last update" age.
- Release: merged; on localhost, a fresh `./v3 start` and an import of zenmemes.com from the page. Production needs no change, since its cron worker is fresh.

## Risks

- **Two workers at once.** The dev-server worker and a launched worker could both run. Validate that the queue's claim and "another worker is active" exit hold. Mitigation: the dev-server worker checks the heartbeat like cron does.
- **A launched worker dies silently.** Its output goes to `/dev/null`. Mitigation: the 15-second start check, and the queue's existing 15-minute recovery for a task stuck as running.
- **Misleading estimates.** Mitigation: show time left only where it is measured or taken from a previous run's timing, and label it as an estimate.
- **Dev-server shutdown leaves a worker running.** Mitigation: stop the worker in the server's shutdown handler; a test checks that no worker is left after stop.

## Shared component inventory

- `DetachedTaskQueueLauncher` (`src/ForumRewrite/TaskQueue/DetachedTaskQueueLauncher.php`): reuse its fixed command and detached start. Add an operator-initiated entry that doesn't check the emergency variable; the existing entry is unchanged.
- `scripts/dev_server.php` and the `start` case in `v3`: add the worker loop and the `--no-worker` flag beside the existing server process handling.
- `InstanceImportTask`, `ImportController`, `tools_import.php` and `import_control.js`: extend, not replace.
- Import output in `scripts/import_instance.php` and `ContentImportRunner`: add `Progress:` lines next to the existing phase lines. `ImportProgress` (`src/ForumRewrite/Import/ImportProgress.php`) stays as it is.
- Queue store progress events: reuse for timing, since each line has a timestamp.

## User flow

1. The operator opens Tools → Import and presses Import.
2. With no worker, the page shows "Starting a worker…"; with one, "Queued…".
3. Within seconds the steps appear: download with bytes and time left, then checking, planning, installing with files and time left, committing, refreshing views and images.
4. The elapsed timer ticks, and "last update" stays a few seconds old while work continues.
5. The page reloads into the result. If nothing started, the page shows the SSH command instead.

## Success criteria

- On a fresh `./v3 start` with no cron, an import from the page completes.
- With the dev-server worker turned off and no cron, the page's launch still completes an import.
- A launch that can't start shows the SSH command within about 20 seconds.
- Install progress counts and time left update at least every few seconds on a 7,000-file import.
- The steps, timer and estimate fit at phone and desktop width in every theme.

## Enhancement requests

- Known-instances list: separate one-shot, waiting on which instances to list, whether to hide the instance's own address, and whether the list ships with the code.

## Study snapshots

- Step 2 handoff: 0.45 projected total, unchanged; scope v2 adds the maintainer's two answers. Baseline `34d87c86`, R1, P2; Claude single exposed rater, 2026-11-17 UTC. R1 hashes not re-verified.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
