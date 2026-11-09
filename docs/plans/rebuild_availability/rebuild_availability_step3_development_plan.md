> **Feature plan:** [Step 1](./rebuild_availability_step1_solution_assessment.md) · [Step 2](./rebuild_availability_step2_feature_description.md) · [Step 3](./rebuild_availability_step3_development_plan.md) · [Step 4](./rebuild_availability_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./rebuild_availability_debug_log.md))

Effort study: `railspike-effort` / `v3/rebuild_availability`; [host ledger](../effort_estimation_trial.md#cycle-v3rebuild_availability). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 2 approved on 2026-11-09; Step 3 drafted; implementation unapproved.

**Cycle estimate: 0.65** (revised; Step 2 0.60)

## Completion Contract

- Normal entry: visitors load pages while a retraction, `./v3 rebuild` or a queued rebuild runs; the operator taps Deploy now or runs `./v3 deploy`.
- End-to-end outcome: during a full rebuild, pages load with the old data at normal speed. When the read model cannot answer within 2 seconds, pages show the busy page (503, `Retry-After: 10`, 10-second reload). Deploy now returns as fast as today; a schema change queues a rebuild that the Deploy page tracks to completion.
- Required recovery: if no worker starts, the Deploy page says the rebuild is waiting and names `./v3 rebuild`; the operator runs it over SSH and reloads the page. A write committed during a build is present after the swap.
- Deployment/external verification: one full rebuild and one schema-changing deploy on zenmemes.com; one full rebuild on qdb.us.
- Release condition: local acceptance on a qdb-sized copy, then both hosts, with timings recorded in the Step 4 summary.

## Key Risks

- **High risk:** a write lands during a build and the swap drops it. Validate in Stage 4 with a test that commits mid-build. Mitigate: promotion already refuses a file behind `HEAD`; rebuild again instead of swapping.
- **High risk:** qdb lacks disk space for two copies. Validate in Stage 1. Mitigate: report before Stage 3; fall back to Option C (fail fast only) on that host.
- Deploy now runs the old code, which cannot know the new schema. Validate in Stage 6. Mitigate: start a detached process of the new code to compare schema and queue the rebuild.
- Background launch is switched off on a host. Validate in Stage 1. Mitigate: the Deploy page names the manual command.

## Stage 1

**Estimate: 0.04**

- Goal: settle the two host risks before dependent work.
- Dependencies: none; the operator runs read-only commands on both hosts.
- Expected changes: none in code; record read-model size, free space, `FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED` and worker heartbeat per host in the Step 4 summary.
- Verification approach: `du`, `df` and `./v3 status` output pasted by the operator.
- Estimate details: driver: operator round trip; uncertainty: low.
- Risks or open questions: if qdb space is short, return to the maintainer before Stage 3.
- Canonical components/API contracts touched: `scripts/status.php` (read only).

## Stage 2

**Estimate: 0.08**

- Goal: a locked read model gives the busy page within 2 seconds, never a hang.
- Dependencies: none.
- Expected changes: `ReadModelConnection::open()` sets a 2-second wait. `FrontController` treats a locked-database error like a lock timeout. `renderBusyError()` and `renderReadModelRecovery()` both send `Retry-After: 10` and a 10-second reload.
- Verification approach: new test holds an exclusive SQLite lock, requests a page, and expects 503 with both headers within 3 seconds; smoke set.
- Estimate details: driver: error matching across PDO messages; uncertainty: low.
- Risks or open questions: CLI commands share the connection; confirm they still wait long enough or pass their own limit.
- Canonical components/API contracts touched: `ReadModelConnection`, `FrontController` busy and recovery pages, `ProfilePresentationContent::busy()` (copy unchanged).

## Stage 3

**Estimate: 0.08**

- Goal: a write that triggers a full rebuild no longer blocks readers.
- Dependencies: Stage 1 (space).
- Expected changes: `LocalWriteService::rebuildReadModel()` builds with `ReadModelCandidateBuilder` and swaps with `promoteWhileLocked()`; it already holds the write lock, so no write can land mid-build.
- Verification approach: test opens a reader on the live file, runs a retraction, and reads old rows during the rebuild and new rows after; retraction and write tests from the smoke set.
- Estimate details: driver: timings and stale-marker handling move to the candidate path; uncertainty: low.
- Risks or open questions: leftover candidate files after a crash; confirm the builder's cleanup covers them.
- Canonical components/API contracts touched: `ReadModelCandidateBuilder`, `ReadModelCandidatePromoter`, `LocalWriteService::refreshDerivedStateAfterCommit()`.

## Stage 4

**Estimate: 0.12**

- Goal: queued rebuilds block neither readers nor writers, and lose no write.
- Dependencies: Stage 3.
- Expected changes: `ReadModelRebuildTaskHandler` builds without the execution lock, then takes it and promotes. When the file is behind `HEAD`, it builds again; after two misses it builds once more under the lock.
- Verification approach: test commits a post while a build runs and finds it after promotion; `TaskQueueWorkerTest`, `ReadModelCandidateBuilderTest`.
- Estimate details: driver: test hook to commit mid-build; uncertainty: medium.
- Risks or open questions:
  - Impact: busy sites could rebuild repeatedly.
  - Early warning / validation: the test counts builds.
  - Mitigation: the third build runs under the lock, so it always ends.
- Canonical components/API contracts touched: `ReadModelRebuildTaskHandler`, `ReadModelCandidatePromoter::promoteWhileLocked()`.

## Stage 5

**Estimate: 0.14**

- Goal: page requests never rebuild.
- Dependencies: Stages 2 and 4.
- Expected changes: `Application::ensureReadModel()` serves the existing file when only the stale marker or `HEAD` differs, and requests a rebuild. A schema mismatch requests a rebuild and shows the recovery page at once. A missing file still builds in the request, since there is nothing to serve. The queue-and-launch code in `requestReadModelSchemaRecovery()` moves to one helper, called with reasons `read_model_stale` and `read_model_schema`.
- Verification approach: tests for each case: stale (old data, one queued task), schema mismatch (recovery page, one task), missing (built in request); repeat requests queue no extra task.
- Estimate details: driver: three request paths and the shared helper; uncertainty: medium.
- Risks or open questions: incremental writes update `repository_head` (`IncrementalReadModelUpdater`), so a normal write does not look stale; confirm with a test.
- Canonical components/API contracts touched: `Application::ensureReadModel()`, `SqliteTaskQueueStore::requestAutomaticRebuild()`, `DetachedTaskQueueLauncher`, `FrontController::requestReadModelSchemaRecovery()`.

## Stage 6

**Estimate: 0.10**

- Goal: deploy stays fast and queues its own rebuild.
- Dependencies: Stage 5.
- Expected changes: after a successful fast-forward, `AppUpdate::runDeploy()` starts a detached process of the new code that compares schema identity and requests a rebuild on a mismatch. The deploy result records that the check started. `./v3 deploy` prints the same.
- Verification approach: `AppUpdateTest` with a fake launcher: deploy returns without waiting; a schema bump queues one rebuild; no bump queues none.
- Estimate details: driver: running new code from an old-code request; uncertainty: medium.
- Risks or open questions: launch switched off; the check then runs on the next worker run or the next page request (Stage 5).
- Canonical components/API contracts touched: `AppUpdate::runDeploy()`, `scripts/deploy.php`, `DetachedTaskQueueLauncher`.

## Stage 7

**Estimate: 0.07**

- Goal: the Deploy page shows the queued rebuild until it finishes.
- Dependencies: Stage 6.
- Expected changes: `DeployController` status adds the rebuild task's state and latest progress line; the template shows it under the deploy result; `deploy_control.js` keeps polling while it is queued or running. When the worker heartbeat is stale, the page names `./v3 rebuild`.
- Verification approach: `DeployPageTest` for queued, running, done and stale-worker states; browser check at 390 px.
- Estimate details: driver: page states; uncertainty: low.
- Risks or open questions: none beyond Stage 6.
- Canonical components/API contracts touched: `DeployController`, `templates/pages/deploy.php`, `public/assets/deploy_control.js`.

## Stage 8

**Estimate: 0.10**

- Goal: docs, concepts and host acceptance.
- Dependencies: Stages 1–7.
- Expected changes: update [read-model rebuild](../../concepts/read_model_rebuild.md), [application update](../../concepts/application_update.md), [internal task queue](../../concepts/internal_task_queue.md); add a *busy page* concept entry; update `docs/runbooks/production_deploy.md`.
- Verification approach: on a qdb-sized copy, time page loads during a rebuild. On zenmemes.com, load pages during a rebuild and deploy a schema change from the phone, watching the Deploy page. On qdb.us, one rebuild with page loads. Replay the no-worker recovery once. Final diff review; smoke plus touched tests.
- Estimate details: driver: host round trips; uncertainty: medium.
- Risks or open questions: host results that miss the targets go back to the maintainer before closeout.
- Canonical components/API contracts touched: concept entries and runbook only.

## Concepts Touched

[Derived read-model rebuild](../../concepts/read_model_rebuild.md) changed (Stages 3–5); [application update](../../concepts/application_update.md) changed (Stages 6–7); [internal task queue](../../concepts/internal_task_queue.md) changed (Stage 5 reasons); *busy page* proposed, added in Stage 8.

## Study Checkpoint

2026-11-09T18:55:00Z; scope v1/B, starting revision `6ff90b5`, R1/P2; R1 hashes not re-verified. User explicitly approved Step 2. Whole-cycle revised **0.60 → 0.65** (new understanding: Deploy now runs the old code, so the schema check needs a detached new-code process; two misses before a locked build). Stage work 0.04/0.08/0.08/0.12/0.14/0.10/0.07/0.10, non-additive. Claude, single exposed rater. Await Approved Step 3.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
