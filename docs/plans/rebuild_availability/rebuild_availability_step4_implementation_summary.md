> **Feature plan:** [Step 1](./rebuild_availability_step1_solution_assessment.md) · [Step 2](./rebuild_availability_step2_feature_description.md) · [Step 3](./rebuild_availability_step3_development_plan.md) · [Step 4](./rebuild_availability_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./rebuild_availability_debug_log.md))

Effort study: `railspike-effort` / `v3/rebuild_availability`; [host ledger](../effort_estimation_trial.md#cycle-v3rebuild_availability). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 3 approved on 2026-11-09; Stages 1–6 complete (qdb heartbeat pending); Stage 7 next.

**Cycle estimate: 0.65** (Step 3, reaffirmed at entry)

## Step 4 Entry

- Branch `feat/rebuild-availability` from `origin/main` at `6ff90b5`; planning commit `331ea1d` holds approved Steps 1–3 and the debug log, moved into this folder with links repaired, plus the plan index entry and a checklist link.
- The Step 1–3 drafts were earlier pushed to `plans/rebuild_availability` at the session stop hook's request. That branch is superseded by this one.
- Whole-cycle **0.65** reaffirmed, 2026-11-09T18:50:25Z. Stage originals copied from Step 3: 0.04/0.08/0.08/0.12/0.14/0.10/0.07/0.10, non-additive. Claude, single exposed rater; copying is not a new rating.
- Checkpoint times in the Step 1–3 artifacts were estimated and run late by about 10 minutes; the ledger records corrected approximate times.
- Related feedback items, not claimed by this cycle: PF-20261021-B05 (QDB "temporarily busy", cause unconfirmed), PF-20261030-B16 (busy API returns HTML; API responses are out of scope here).

## Stage 1 - Host preflight

**Original estimate: 0.04**
**Retrospective: 0.04**

- Changes: none in code.
- Verification (operator output pasted 2026-11-09, about 19:07 UTC; host names left out on request):
  - zenmemes.com: read model ready, schema 53/53, no stale marker, no rebuild task; task executor fresh (cron ran within the last minute); automatic recovery idle. Read model 12M at the default path; 83G free. No `FORUM_TASK_QUEUE_EMERGENCY_LAUNCH_ENABLED` in the searched files. Passed.
  - qdb.us: read model 60M at the default path; 4.7T free. The flag search printed nothing. The `./v3 status` line did not run in the pasted session, so the executor heartbeat there is pending; Stage 8 rechecks it.
- Notes:
  - Disk-space risk resolved: a second copy fits easily on both hosts, so Stage 3 proceeds as planned.
  - Detached launch is off on both hosts, but cron runs the worker every minute on zenmemes.com. Queued rebuilds therefore start within about a minute there without any detached launch. Stage 6 should rely on the queue first and use the detached launch only where it is enabled; that is a refinement inside Stage 6's approved goal.
- Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Stage 2 - Busy page within 2 seconds

**Original estimate: 0.08**
**Retrospective: 0.08**

- Changes:
  - `ReadModelConnection` takes an optional wait limit and sets SQLite's busy timeout from it; `PAGE_REQUEST_WAIT_MILLISECONDS` is 2000. `isLocked()` recognizes a locked-database error, including when wrapped.
  - `Application` and `RouteServices` pass the limit to the request's read connection. Only `FrontController` sets it, so CLI commands, static builds and writers keep the driver's 60-second default (the Step 3 risk about CLI callers).
  - `Application::ensureReadModel()` rethrows a locked error instead of treating it as an unreadable file. Before this, a short wait would have triggered a rebuild inside the request.
  - `FrontController` shows the busy page for a locked read model as well as for an execution-lock timeout. The busy page and the update page now send `Retry-After: 10` and reload after 10 seconds. The high-traffic busy response uses the same helper (it previously sent `Retry-After: 5`).
  - New `tests/ReadModelBusyPageTest.php`, registered in `tests/run.php`.
- Verification:
  - `./v3 test --smoke ReadModelBusyPageTest SingleRequestHttpTest LocalAppSmokeTest`: 443 run, 443 passed. Full suite not run.
  - The locked-page test took 2.18s and leaves the read-model file unchanged. With the limit raised to 8 seconds, the same test failed at 8.15s, so it measures the limit.
  - API responses are unchanged: `FrontController` still rethrows API errors, which already return JSON with `Retry-After: 10`.
- Notes: the busy-page copy comes from the site profile, unchanged.
- Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Stage 3 - Write-time rebuilds build and swap

**Original estimate: 0.08**
**Retrospective: 0.10**

- Changes:
  - `LocalWriteService::rebuildReadModel()` builds a new file with `ReadModelCandidateBuilder` and swaps it in with `ReadModelCandidatePromoter::promoteWhileLocked()`. Callers already hold the write lock, so no write lands between build and swap. If the swap is refused (for example a leftover SQLite journal beside the live file), it falls back to the old in-place rebuild.
  - `ReadModelCandidateBuilder::timings()` returns the build's phase timings, so write results keep reporting them.
  - `RouteServices::pdo()` reopens its cached connection when the live file was swapped during the request. Without this, a request that writes and then renders would read the old file.
  - New test `WriteApiSmokeTest::testRetractionRebuildSwapsInNewFileSoOpenReadersKeepOldData`: a reader opened before a retraction still sees the old row, the live file is a new inode, no candidate file is left, and a fresh read and the page show the retraction.
  - Two existing tests held one connection across a write that rebuilds and expected the new data through it. They now reopen the connection after the rebuild; that expectation is exactly the in-place behaviour this cycle removes.
- Verification:
  - `./v3 test --smoke WriteApiSmokeTest ReadModelBusyPageTest`: 650 run; 648 passed and 2 failed before the test fix (both passed on the base commit, so the change caused them); both pass after reopening.
  - `./v3 test --smoke` with the 12 other test classes that construct `LocalWriteService`: 493 run, 493 passed. With `CanonicalRecordParsersTest LocalAppSmokeTest SingleRequestPostingTest`: 449 run, 449 passed. Full suite not run.
- Notes:
  - Behaviour change for any code holding a read-model connection across a full rebuild: it keeps reading the old file. Production request code reaches the read model through `RouteServices::pdo()` (now reopened) or opens fresh connections in `LocalWriteService`.
  - Retrospective above original: the reopen in `RouteServices` and the two test updates were not planned.
- Estimate details: original scope as in Step 3; no revision before work.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Stage 4 - Queued rebuilds lose no writes

**Original estimate: 0.12**
**Retrospective: 0.10**

- Changes:
  - `ReadModelRebuildTaskHandler` builds a new file without the write lock, then takes the lock and swaps it in. When commits landed during the build, the new file is behind `HEAD` and is not swapped in; it builds again. After two misses (`UNLOCKED_ATTEMPTS`) it builds once under the lock, which always finishes. It ends with the progress line `Read model: rebuild complete; new file in use`.
  - The lock wait is now the normal 5-second limit instead of 0, so a brief write no longer fails the rebuild task outright.
  - New `ReadModelCandidateBehindHead` exception, thrown by `ReadModelCandidateBuilder::assertValid()` only when the file is behind `HEAD`. Other validation failures keep the plain `RuntimeException` and message, so existing callers (`./v3 rebuild`, imports) behave as before.
  - New `tests/ReadModelRebuildTaskHandlerTest.php`, registered in `tests/run.php`: a commit during one build is present after the swap (one retry); commits during every unlocked build end with the locked build; a reader during the build reads the old file without waiting.
- Verification:
  - `./v3 test --smoke ReadModelRebuildTaskHandlerTest TaskQueueWorkerTest TaskQueueCommandTest ReadModelCandidateBuilderTest StatusCommandTest`: 467 run, 467 passed. Full suite not run.
  - The first run failed `TaskQueueCommandTest::testRebuildWorkerRecordsPrivateProgressCheckpoints`: its last progress line came from the promoter and did not start with `Read model:`. The final progress line above fixed it and makes `./v3 task-queue status` end on a clear message.
- Notes: under the locked build, writes wait up to 5 seconds and then get the busy response, as before this cycle; it only happens after two misses.
- Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Stage 5 - Page requests hand rebuilds to the queue

**Original estimate: 0.14**
**Retrospective: 0.18**

- Changes:
  - New `AutomaticReadModelRebuild` (task queue): `workerAvailable()` is true when the cron heartbeat is fresh or detached launch is enabled; `request($reason)` queues one rebuild and starts a detached worker when cron is not running. It replaces the queue-and-launch code that lived in `FrontController::requestReadModelSchemaRecovery()`, which now delegates to it. Reasons: `read_model_stale` (new) and `read_model_schema` (existing).
  - `Application::ensureReadModel()` now decides per case:
    - missing file: build in the request (nothing older to show);
    - stale marker or behind `HEAD`, same schema: show the existing data and queue a rebuild;
    - behind `HEAD`: first wait for any write in progress (the normal lock wait); a write commits before it updates the read model, so this is usually already caught up;
    - old schema, other root or unreadable: queue a rebuild and throw the new `ReadModelUnavailable`, which `FrontController` shows as the update page (503, `Retry-After: 10`, reload);
    - no worker available (no fresh heartbeat, no detached launch): rebuild in the request as before, but by building a new file and swapping it in.
  - New `tests/ReadModelRequestRebuildTest.php` (five cases above), registered in `tests/run.php`.
  - Twelve existing startup tests held a connection opened before the startup rebuild and read the new schema through it. They now reopen after the request, as two older tests already did.
- Verification:
  - `./v3 test --smoke` with every test class that mentions `schema_version`, the stale marker, `FrontController`, `repository_head` or the task queue (54 classes, `WriteApiSmokeTest` run separately): 730 run, 730 passed. `WriteApiSmokeTest` with this cycle's new test classes: 658 run, 658 passed. Full suite not run.
  - Parallel runs (`--jobs=4`) of `WriteApiSmokeTest` fail intermittently both on the Stage 4 commit and here; parallel processes share `/tmp` lock and signature-cache files and the checkout's `state/private`. An early version of this stage skipped waiting when another process held the lock; under the shared `/tmp` lock that served stale data and failed operator tests every parallel run, so it now waits instead. After that change, two parallel runs gave one pass and one failure (`testReadableSignedExpansionOfLegacyInitialPreservesHistoricalBytes`, "attempt to write a readonly database"); that test passed three times alone. Recorded for the maintainer's full run.
- Notes:
  - Deviation from Step 2's "page requests never rebuild": with no worker at all (local development, tests, a host without cron), requests still rebuild, because nothing else would. On hosts with cron, requests never rebuild. Asking the maintainer to confirm.
  - Retrospective above original: the wait-for-write fix and twelve test updates were not planned.
- Estimate details: original scope as in Step 3; no revision before work.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Stage 6 - Deploy queues its own rebuild

**Original estimate: 0.10**
**Retrospective: 0.08**

- Changes:
  - After a successful fast-forward, `AppUpdate::deploy()` runs `scripts/task_queue.php check-read-model` as a short process of the new code. It reads only the read model's metadata; when the schema is not the one the new code expects, it queues a rebuild through `AutomaticReadModelRebuild` (which starts a detached worker only where cron is not running and launch is enabled). Deploys that change nothing, fail or are refused skip the check.
  - The attempt log gains `Read-Model: current|queued|unknown`; a queued rebuild is also added to the attempt message. When the check cannot run, the message names `./v3 rebuild`.
  - New subcommand `./v3 task-queue check-read-model`, listed in its usage text. `AppUpdate` takes an optional check closure for tests, and its process runner now serves both Git and this check.
- Verification:
  - New tests: `AppUpdateTest::testDeployChecksTheReadModelWithTheNewCodeAndQueuesARebuild`, `testDeployWithoutChangesSkipsTheReadModelCheck`, `testDeployWithoutTheCheckScriptRecordsUnknown`; `TaskQueueCommandTest::testCheckReadModelQueuesARebuildOnlyForAnOldSchema` (current schema queues nothing; old schema queues one rebuild, repeat runs reuse it).
  - `./v3 test --smoke AppUpdateTest TaskQueueCommandTest DeployPageTest`: 487 run, 487 passed. Full suite not run.
  - The real check, run against this checkout's code: 0.04s.
- Notes: Stage 1 refinement applied. The plan named a detached process for the check; a short synchronous process of the new code is simpler and still adds well under a second. Detached launch stays only as the existing fallback when cron is not running.
- Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
