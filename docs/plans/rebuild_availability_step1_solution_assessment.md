> **Feature plan:** [Step 1](./rebuild_availability_step1_solution_assessment.md) · [Step 2](./rebuild_availability_step2_feature_description.md) · [Step 3](./rebuild_availability_step3_development_plan.md) · [Step 4](./rebuild_availability_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./rebuild_availability_debug_log.md))

Effort study: `railspike-effort` / `v3/rebuild_availability`; [host ledger](./effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.55

## Original Query

Why does a retraction cause the site to do a reindex of some sort (does it?) I just did a retraction and it's taking a while to respond, so I'm assuming that's what it is. How do we fix this? Also, what other common activities can cause this, and how do we stop it?

There's also the issue of a rebuild causing loading stalls rather than a ~"come back soon" message.

[On running the read-model rebuild as part of deploy:] For this one, I would like for it to be done in two steps, because I like how fast the "deploy now" happens right now. So we finish the deploy, and then trigger the rebuild via a background request?

Whatever order you think makes the more sense, please make a short checklist document of the cycles we'll run.

Great job, please commit it to main and then write step 1 of the first cycle.

## Understood Intent

This is cycle 1 of the [read-model rebuild checklist](./read_model_rebuild_checklist.md). While a full read-model rebuild runs, visitors should get their page or a quick "come back soon" page, never a hang. Deploy now must stay as fast as it is today; a deploy that changes the read-model schema queues its rebuild instead of running it. Making rebuilds rarer belongs to cycles 2 and 3.

## Problem Statement

A full rebuild rewrites the live read-model file inside one long transaction, so page requests wait up to 60 seconds for it and never reach the busy page.

## Where the stall comes from (from source, not yet measured on a host)

- `ReadModelBuilder::rebuild()` drops and recreates every table in the live file. The read model uses SQLite's default journal mode, so once the rebuild needs an exclusive lock, readers are blocked.
- `ReadModelConnection::open()` sets no timeout, so PHP's SQLite driver waits up to its 60-second default.
- `FrontController` shows `renderBusyError()` only when the execution lock times out (5 seconds), not when SQLite is locked.
- `ensureReadModel()` runs on every request. When the read model is behind `HEAD` or its schema is old, a page request tries to rebuild by itself and waits 5 seconds for the lock first.
- Pieces already exist: `ReadModelCandidateBuilder` and `ReadModelCandidatePromoter` build a separate file and rename it over the live one (used by `./v3 rebuild` and imports). `requestReadModelSchemaRecovery()` already queues a rebuild and launches a detached worker, but only for one old error message. `ReadModelRebuildTaskHandler` rebuilds in place.

## Concepts touched

- All options: [derived read-model rebuild](../concepts/read_model_rebuild.md) (changed), [application update](../concepts/application_update.md) (changed: queues a rebuild after a schema change), [internal task queue](../concepts/internal_task_queue.md) (used unchanged); proposed: *busy page* (what a visitor sees while the read model is unavailable).
- Option A also changes how the read model is published as a download.

## Solution Options

### Option A: WAL mode and fail fast

**Estimate: 0.50**

Switch the read model to WAL mode, so readers keep seeing the last committed data while a rebuild runs. Give read connections a short timeout and show the busy page when SQLite is locked. Page requests stop rebuilding by themselves. Deploy compares schema identity and queues a rebuild when it changed.

- Pros: one setting removes most reader blocking; in-place incremental updates also stop blocking readers.
- Cons: `ReadModelCandidatePromoter` refuses to run while SQLite sidecar files exist, so `./v3 rebuild` and imports need rework. The published `/downloads/read_model.sqlite3` needs a checkpoint first. WAL needs shared memory on a local filesystem, which the qdb shared host may not give.
- Driver: making WAL coexist with candidate promotion and the download. Uncertainty: the qdb filesystem.

### Option B: Build a new file and swap it in, plus fail fast

**Estimate: 0.55**

Route every full rebuild through the existing candidate builder and promoter: write a new file, then rename it over the live one. Readers keep the old file until the rename, so a rebuild never blocks them. Add the short timeout and busy page as a safety net. Page requests stop rebuilding by themselves. Deploy compares schema identity, queues a rebuild and launches a detached worker.

- Pros: reuses code that already runs on the qdb host; works with the current journal mode and the download; readers see either the old or the new model, never a half-built one.
- Cons: needs disk space for two copies during a rebuild. A write that rebuilds in the request still holds the writer for minutes (cycle 3 fixes that). After a schema change, the new code cannot read the old file, so visitors see the busy page until the queued rebuild finishes.
- Driver: moving the three in-place rebuild callers (`LocalWriteService::rebuildReadModel()`, `ensureReadModel()`, the task handler) onto the promoter. Uncertainty: rename behaviour with open readers on the qdb host.

### Option C: Fail fast only

**Estimate: 0.30**

Keep rebuilding in place. Add the short timeout and busy page, stop page requests from rebuilding, and add the deploy step.

- Pros: smallest change; every hang becomes a busy page within about two seconds.
- Cons: visitors still see the busy page for the whole rebuild, minutes on qdb, even when the old data would have been fine to show.
- Driver: busy-page handling for a locked database.

## Recommendation

**Option B.** It turns a rebuild from "pages hang" into "pages keep working" using code that already exists, and it avoids WAL's conflicts with promotion, the download and the shared host. The fail-fast pieces and the deploy step are the same in all three options. Option B also completes cycle 3's swap item, which shrinks that cycle.

Step 2 should settle the busy-page wording and refresh interval, how long a reader waits before giving up, and what the Deploy page shows while the queued rebuild runs.

**Vertical-slice viability:** on a qdb-sized copy, start a full rebuild. While it runs, a thread page loads with the old data in under a second. With the database forced locked, the same page shows the busy page within two seconds. A deploy that bumps the schema returns as fast as today and the Deploy page shows the queued rebuild.

## Enhancement Requests

- Two-step deploy: finish the deploy, then trigger the rebuild in the background. Disposition: in scope for this cycle (all options). Approval: requested by the maintainer; not Approved Step 1.
- Checklist edit: the committed checklist first named WAL for this cycle. Disposition: changed to "WAL or build-and-swap, chosen in Step 1" after finding the promoter's sidecar check, before commit `6ff90b5`.

## Study Checkpoint

2026-11-09T18:25:00Z; scope v1; starting revision `6ff90b5` (checklist commit on `ddf8fa5`); reference R1, hashes not re-verified. Claude, single exposed rater; the retraction, rebuild, lock and deploy code was read before rating, so no pristine intake is claimed.

- Intake after inspection and Step 1 handoff: **0.55** projected total for Option B, including the deploy step and host checks.
- Alternatives: A 0.50, C 0.30, non-additive. Not hours or measured labor; no implementation, accepted outcome or approval to advance.

Status: Step 1 approved (Option B) on 2026-11-09; Step 2 drafted. The draft was committed and pushed to branch `plans/rebuild_availability` (not `main`) at the session stop hook's request; that commit was not approval.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
