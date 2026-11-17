# Import-instance GUI Step 1 solution assessment

> **Feature plan:** [Step 1](./import_instance_gui_step1_solution_assessment.md) · [Step 2](./import_instance_gui_step2_feature_description.md) · [Step 3](./import_instance_gui_step3_development_plan.md) · [Step 4](./import_instance_gui_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/import_instance_gui`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

Cycle estimate: 0.70

## Original Query

import-instance needs a gui, please

(Source: https://zenmemes.com/threads/thread-20261113042243-1cbe8b8a, posted by ilyag on 2026-11-13 UTC with no body. Pasted into a session with "please help".)

## Understood Intent

A root operator should be able to import another instance's public content from the site, without SSH. Today the only entry is `./v3 import-instance` on the server, and the [import runbook](../../runbooks/instance_content_import.md) says there is no web UI. The likely flow is: enter a source, preview, read the result, import, and resume after an interruption.

## Problem

Importing needs a shell on the server, so an operator on a phone cannot preview or run an import or see why the last one stopped.

Concepts touched: [Instance content import](../../concepts/instance_content_import.md) (new entry point only; merge rules unchanged), [Internal task queue](../../concepts/internal_task_queue.md) (Option A), [Operator command progress](../../concepts/operator_command_progress.md), [Tools launcher](../../concepts/tools_launcher.md).

## Option A — Tools → Import page that queues the import for the task worker
**Estimate: 0.70** — driver: a new import task type with progress and report capture; uncertainty: how long a real import holds the worker.

Add a root-only Tools → Import page modeled on Tools → Deploy. The operator enters a source and presses Preview or Import. The page queues a task, and the existing task worker runs the same import code in CLI context. The page polls status, shows the progress lines, and then shows the result counts and review causes. When an interrupted import is pending, the page offers Resume.

- Reuses the Deploy page contract: server-side root gate, same-origin check, JSON status polling and worker heartbeat display.
- Runs where PCNTL, `setsid` and the time-limit supervisor already work, so the import's safety limits stay intact.
- Survives a dropped phone connection, because the browser only starts and watches the task.
- Needs a running worker; the page says so when the heartbeat is missing, as Deploy does.
- A long import blocks other queued tasks while it runs.

## Option B — Page starts `./v3 import-instance` as a detached process
**Estimate: 0.50** — driver: process launch and a private output log the page reads; uncertainty: hosts that disable `proc_open` for the web server.

The page starts the unchanged command in the background and writes its output to a private log. The page reads that log until the command exits.

- Leaves the import command and its locking untouched.
- Works without the task worker.
- Shared hosts such as the qdb.us host may block background processes from the web server.
- Adds a second way to launch operator work outside the queue, with its own cleanup and stale-log rules.

## Option C — Read-only status page plus a command builder
**Estimate: 0.25** — driver: reading the last report and pending-resume state; uncertainty: low.

The page shows the last import's result, review causes and any pending resume. A small form builds the exact command for the operator to paste into SSH.

- Smallest change, and the browser never writes to production content.
- Does not remove the SSH step, so it falls short of the request.

## Recommendation

Option A, with Option C's "last import" view on the same page. First slice: Preview, Import and Resume for a typed URL or hostname, live progress, and the result summary. The page stays behind a feature flag and the existing root-operator gate. Named sources from a private `--sources` file and advanced path options stay CLI-only for now. Option B is the fallback if the maintainer does not want imports in the task queue.

Open questions for the maintainer:

1. Should Import require a fresh Preview of the same source first? I'd go with yes.
2. Should the page also be the place to read the full per-file report (the `--verbose` view)? I'd show grouped causes only, with per-file details folded.

Enhancement requests: none recorded.

## Study snapshots

- Intake (after reading the import script, Deploy controller and task queue; no pristine intake): 0.70 projected total for the recommended scope. Baseline `6bfe1243`, scope v1, R1, P2; Claude single exposed rater, 2026-11-13 UTC.
- Step 1 handoff: 0.70 reaffirmed for Option A with C's status view. R1 hashes not re-verified in this session. Option estimates are independent and not summed.

*— Claude · 2026-11-13 UTC · Model: Claude Opus 5.5*
