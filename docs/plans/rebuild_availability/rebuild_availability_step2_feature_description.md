> **Feature plan:** [Step 1](./rebuild_availability_step1_solution_assessment.md) · [Step 2](./rebuild_availability_step2_feature_description.md) · [Step 3](./rebuild_availability_step3_development_plan.md) · [Step 4](./rebuild_availability_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./rebuild_availability_debug_log.md))

Effort study: `railspike-effort` / `v3/rebuild_availability`; [host ledger](../effort_estimation_trial.md#cycle-v3rebuild_availability). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 2 approved on 2026-11-09; Step 3 drafted; implementation unapproved.

**Cycle estimate: 0.60** (revised; Step 1 0.55)

## Problem

While a full read-model rebuild runs, page requests hang for up to 60 seconds instead of loading or showing a "come back soon" page. A deploy that changes the read-model schema leaves the first visitors to pay for the rebuild.

## User Stories

- As a visitor, I want pages to keep loading while the site rebuilds its data so that a retraction or deploy elsewhere does not freeze my reading.
- As a visitor, I want a short "back soon" page that retries by itself when the site truly cannot answer so that I am not left staring at a spinner.
- As the operator, I want Deploy now to finish as fast as it does today so that deploying from my phone stays quick.
- As the operator, I want the Deploy page to show a rebuild the deploy queued, until it finishes, so that I know when the site is fully back.

## Core Requirements

- **Readers never wait on a full rebuild.** Every full rebuild writes a new read-model file and swaps it in when it is complete. Until the swap, pages read the old file at normal speed.
- **Fail fast.** A page request waits at most 2 seconds for the read model. After that, it shows the busy page with HTTP 503, `Retry-After: 10` and a 10-second automatic reload.
- **Page requests never rebuild.** A request that finds the read model missing, stale or on an old schema queues a rebuild (once) and shows the busy page. It serves the old file when the schema still matches.
- **Two-step deploy.** Deploy now, queued Deploy and `./v3 deploy` fast-forward as today, then compare schema identity. On a mismatch they queue a rebuild, start a background worker and return. The Deploy page shows that rebuild until it finishes.
- **No lost writes.** A write that commits while a new file is being built must still appear after the swap. If the new file is behind `HEAD`, it is not swapped in silently; the read model is marked stale and rebuilt again.

## Delivery Scope and Completion Boundary

Work type: application change, plus touched concept entries, the production deploy runbook and this cycle's records and study-ledger bookkeeping. No new dependencies. Out of scope: making retractions and profile names incremental (cycle 2), and moving write-triggered rebuilds out of the writer's request (cycle 3). A writer whose write triggers a full rebuild still waits for it in this cycle.

Completion boundary: on zenmemes.com, the operator starts a full rebuild and loads several thread pages while it runs; each loads with the old data in under a second. With the read model forced locked, the same pages show the busy page within 2 seconds and reload by themselves. Deploy now of a commit that bumps the schema returns in about the usual time; the Deploy page then shows the queued rebuild until it finishes. Release condition: verified on a qdb-sized copy locally, then on zenmemes.com, then one rebuild on qdb.us.

Known environments: the operator deploys zenmemes.com from an iPhone (Deploy now, or SSH from the phone), and runs qdb.us on a shared host over SSH. If the background worker does not start, the Deploy page says the rebuild is waiting for the worker; the operator runs `./v3 rebuild` over SSH and reloads the Deploy page to confirm. Evidence is page timings during a rebuild and the Deploy page states on each host; local runs do not satisfy the host checks.

## Shared Component Inventory

- **Build-and-swap** (`ReadModelCandidateBuilder`, `ReadModelCandidatePromoter`, used by `scripts/rebuild_read_model.php` and imports): reuse for every full rebuild; extend only to handle a new file that ended up behind `HEAD`.
- **In-place rebuild callers** (`LocalWriteService::rebuildReadModel()`, `Application::ensureReadModel()`, `ReadModelRebuildTaskHandler`): move onto build-and-swap; no new rebuild entry point.
- **Busy pages** (`FrontController::renderBusyError()` with the profile's busy copy in `ProfilePresentationContent::busy()`, and `renderReadModelRecovery()` with its updating/rebuilding states): reuse both; add the reload and `Retry-After` where missing. No new page.
- **Automatic rebuild request** (`SqliteTaskQueueStore::requestAutomaticRebuild()`, detached launch in `requestReadModelSchemaRecovery()`, `DetachedTaskQueueLauncher`): generalize from the one old error message to any stale or old-schema read model.
- **Deploy routine and page** (`AppUpdate::runDeploy()`, `DeployController`, `templates/pages/deploy.php`, `public/assets/deploy_control.js`): extend the result and the existing 5-second status poll; no new page.
- **Read connection** (`ReadModelConnection::open()`): add the wait limit here so every reader gets it.

## Risks

- **A write lands during the build.** The swap would drop it. Validate early with a test that commits during a build; mitigate by checking the new file against `HEAD` before the swap and rebuilding again when it is behind.
- **Disk space on the qdb host.** Two copies exist during a build. Check free space against the read-model size on qdb first; if it is tight, report it before Step 3.
- **Background launch is switched off.** `DetachedTaskQueueLauncher` needs `FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED`. Confirm the setting and cron on both hosts; the Deploy page names the manual fallback when no worker starts.
- **Schema-change window.** New code cannot read an old-schema file, so visitors see the busy page until the queued rebuild finishes. Measure that window on qdb; it is accepted, not removed, in this cycle.

## Simple User Flow

1. A retraction triggers a full rebuild on zenmemes.com.
2. A visitor opens a thread; it loads at normal speed with the old data.
3. The rebuild finishes and swaps in; the next page load shows the retraction.
4. Later, the operator taps Deploy now for a commit that changes the schema; it returns in seconds and says a rebuild is queued.
5. A visitor during that window sees the busy page, which reloads by itself every 10 seconds.
6. The Deploy page shows the rebuild running, then done; the visitor's page loads on its next reload.

## Success Criteria

- During a full rebuild on a qdb-sized copy, thread pages load in under 1 second.
- With the read model locked, pages show the busy page within 2 seconds, never a hang.
- No page request runs a rebuild; request logs show rebuilds only from the worker, CLI or a write.
- Deploy now of a schema change takes about as long as a deploy without one, and the Deploy page tracks the queued rebuild to completion.
- A write committed during a build is present after the swap.

## Concepts Touched

[Derived read-model rebuild](../../concepts/read_model_rebuild.md) changed (all full rebuilds build and swap; requests never rebuild); [application update](../../concepts/application_update.md) changed (queues a rebuild after a schema change); [internal task queue](../../concepts/internal_task_queue.md) used, possibly changed for the general automatic rebuild request; proposed: *busy page* (what a visitor sees while the read model is unavailable), entry added at implementation.

## Enhancement Requests

- Two-step deploy (from Step 1): in scope; requirement "Two-step deploy".

## Study Checkpoint

2026-11-09T18:40:00Z; scope v1/B, starting revision `6ff90b5`, R1/P2; R1 hashes not re-verified. User explicitly approved Step 1 (Option B). Whole-cycle revised **0.55 → 0.60** (new understanding: a write can land during a build and must not be lost, and the automatic rebuild request needs generalizing from one error message). Claude, single exposed rater. No implementation, measured labor or accepted outcome. Await Approved Step 2.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
