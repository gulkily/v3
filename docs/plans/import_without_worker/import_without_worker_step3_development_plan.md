# Import without a worker Step 3 development plan

> **Feature plan:** [Step 1](./import_without_worker_step1_solution_assessment.md) · [Step 2](./import_without_worker_step2_feature_description.md) · [Step 3](./import_without_worker_step3_development_plan.md) · [Step 4](./import_without_worker_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/import_without_worker`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.46**

Step 2 approved 2026-11-17 ("approved step 2").

## Completion Contract

- Entry: Tools → Import on a fresh `./v3 start` with no cron. Also, with `--no-worker`, the page's own launch.
- Outcome: Import runs to its result. Meanwhile the page shows the steps, a ticking elapsed timer, the age of the last update, and time left where measured.
- Recovery: a worker that doesn't start within about 15 seconds shows the SSH command for that import. A worker killed mid-import leaves Resume and Cancel. A stall shows as a growing last-update age.
- External check: on this workspace, a real import of zenmemes.com from the page on `./v3 start`.
- Release: merged; production needs no change.

## Key Risks

- **High risk:** a root request now starts processes without the emergency variable. Impact: unwanted processes on hosts that forbid them. Validation: a Stage 3 test checks that only the fixed worker command can start, only from the import route, only for root, and only with the Import page flag on. Mitigation: a separate launcher entry with no command input, and the existing entry left unchanged.
- **Two workers.** The queue's lock file already makes a second worker exit ("Another task queue worker is already running."). Stage 2 tests this with the dev-server loop and a launched worker together.
- **Estimates.** Shown only from measured rates, or from the previous import's step timings taken from progress event timestamps; otherwise the page says "estimating".

## Stage 1
**Estimate: 0.07**
- Goal: the import prints structured progress.
- Dependencies: none.
- Expected changes:
  - `Progress: <step> <done>/<total>` lines, where total is `?` when unknown. Steps, in order: `download` (bytes, total from Content-Length), `extract`, `plan`, `install` (files, every 100 and at the end), `commit`, `publish`, `images`. Each step prints a line when it starts and when it ends.
  - Emitted from `scripts/import_instance.php`, `InstanceArchiveDownloader` (existing 1 MiB progress callback) and `ContentImportRunner` (existing phase lines). Current text lines stay.
- Verification approach: an `ImportInstanceCommandTest` case checks the order and counts of the `Progress:` lines on a loopback import. `./v3 test --smoke` with that test.
- Estimate details: driver: threading the Content-Length total to the callback; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: the importer's progress callbacks.

## Stage 2
**Estimate: 0.08**
- Goal: `./v3 start` keeps a worker running.
- Dependencies: none.
- Expected changes:
  - `scripts/dev_server.php` starts `php scripts/task_queue.php run --quiet --limit=1` whenever none is running and at least 10 seconds have passed since the last start. It stops the worker on shutdown. The startup header says "worker on".
  - `v3 start --no-worker` turns it off; usage text, README and CLI reference updated.
- Verification approach:
  - A test starts the dev server on a free port with a temporary queue, queues a no-op task, sees it complete, stops the server and checks no worker process is left.
  - The same check runs with `--no-worker`, where the task stays queued.
  - A second test runs a launched worker while the loop runs and checks that one of them exits with the lock message.
- Estimate details: driver: process lifecycle in the existing select loop; uncertainty: the test harness for the dev server.
- Risks or open questions:
  - Impact: a worker left running after Ctrl+C.
  - Early warning / validation: the leftover-process check.
  - Mitigation: `proc_terminate` in the existing shutdown handler, then kill after 2 seconds.
- Canonical components/API contracts touched: the `dev_server.php` main loop and shutdown handler; the `v3 start` argument parsing.

## Stage 3
**Estimate: 0.10**
- Goal: Import starts a worker when none is fresh, and reports if it doesn't start.
- Dependencies: none (Stage 2 is not required for hosted installs).
- Expected changes:
  - `DetachedTaskQueueLauncher::launchForOperator(): string` uses the same fixed command, skips the emergency variable check, and returns `launched`, `unavailable` or `failed`.
  - `ImportController::submit`, after queuing, calls it when the heartbeat isn't fresh. It records the launch status and time in the request file (`Worker-Launch: launched`, `Worker-Launch-At: …`).
  - `state()` reports `start: waiting|started|not_started`. `not_started` means the task is still queued 15 seconds after the launch, or the launch failed.
  - The page then shows the exact command: `php scripts/task_queue.php run --limit=1`, or `./v3 import-instance <source>` with destination options.
- Verification approach: controller tests with a launcher double for launched, failed and unavailable, plus the 15-second `not_started` path with a fixed clock. A test confirms the emergency entry still refuses without the variable, and that a non-root request never launches.
- Estimate details: driver: threading launch state through request and status; uncertainty: low.
- Risks or open questions: see Key Risks.
- Canonical components/API contracts touched: `DetachedTaskQueueLauncher`, `ImportController::submit/state`, the request file fields.

## Stage 4
**Estimate: 0.15**
- Goal: the page shows steps, liveness and time left.
- Dependencies: Stage 1; Stage 3 for the "Starting a worker…" step.
- Expected changes:
  - `state()` adds `steps`: each step's status and done/total, parsed from `Progress:` lines with event timestamps. It adds `last_update_at`, `started_at`, and `estimate`: seconds left for the current step from its rate, and a whole-import figure when the previous completed import's step durations are available.
  - The template and `import_control.js` render an ordered step list with the current step marked, a client-side elapsed timer and "updated Ns ago", and "about N min left" or "estimating".
  - The status poll returns the same fields.
- Verification approach: unit tests for parsing and the estimate rules (no estimate without a basis; rate-based; previous-run-based); page tests for running markup; browser check across themes at 390 and 1280 px, including the timer ticking with no new lines.
- Estimate details: driver: estimate rules and the step UI; uncertainty: the read-model refresh, which has no count.
- Risks or open questions:
  - Impact: a precise-looking wrong number.
  - Early warning / validation: estimate tests.
  - Mitigation: round to minutes, label it "about", and fall back to "estimating".
- Canonical components/API contracts touched: `ImportController::state`, `tools_import.php`, `import_control.js`, `SqliteTaskQueueStore::taskProgress` (timestamps).

## Stage 5
**Estimate: 0.06**
- Goal: end-to-end check and docs.
- Dependencies: Stages 1–4.
- Expected changes: runbook Browser entry update (worker launch, local worker); concept line in `docs/concepts/internal_task_queue.md` for the operator launch and the dev-server worker; UI copy checklist entry; feedback item note.
- Verification approach: `./v3 start` on a fresh state directory, then an import of zenmemes.com from the page in Chromium, watching steps and estimates. Repeat with `--no-worker` and no cron. Run the browser theme check. Full suite not run.
- Estimate details: driver: the real import's duration; uncertainty: network access to zenmemes.com from the workspace.
- Risks or open questions: if zenmemes.com is unreachable from here, use a local loopback source instead and record that the external check is unverified.
- Canonical components/API contracts touched: none new.

## Study checkpoint

- Step 3 handoff: 0.46 projected total (from 0.45, rounding of stage detail). Scope v2, baseline `936c6357`, R1, P2; Claude single exposed rater, 2026-11-17 UTC. R1 hashes not re-verified. Stage estimates are independent and not summed.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
