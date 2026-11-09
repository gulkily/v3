> **Feature plan:** [Step 1](./high_traffic_post_refresh_step1_solution_assessment.md) · [Step 2](./high_traffic_post_refresh_step2_feature_description.md) · [Step 3](./high_traffic_post_refresh_step3_development_plan.md) · [Step 4](./high_traffic_post_refresh_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/high_traffic_post_refresh`; [host ledger](../effort_estimation_trial.md#cycle-v3high_traffic_post_refresh). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.30** (Step 3 projection, reaffirmed at entry)

Branch: `feat/high_traffic_post_refresh`. Planning commit: `a9754ccc`.

## Step 4 entry

2026-11-09T23:00Z, `railspike-effort` / `v3/high_traffic_post_refresh`, Step 4 entry. Step 3 approved by the maintainer on 2026-11-09 ("approved step 3"). Scope v1 unchanged. Projected total **0.30** reaffirmed. Stage originals copied from Step 3: 0.10, 0.10, 0.05, 0.05, non-additive. Same R1/P2, single exposed Claude rater. Copying is not a new rating.

Starting revision: `a2bcc64d` (`main`). No matching item in `docs/feedback/items/`.

## Stage 1 - Refresh or queue after a post

**Original estimate: 0.10**

**Retrospective stage-work assessment: 0.10**

- Changes:
  - `HighTrafficRefresh::afterPost(threadId)`: returns `refreshed`, `queued`, `skipped` or `inactive`, and never throws. It force-renders a cached thread page with `HighTrafficCache::generate`. On a refusal (busy page lock, no generator slot) or an error, it queues a write hint.
  - `hint(route, changedAt)`: a write hint carries the write time. It is accepted even when the page looks fresh and replaces an older hint with the later time. Browser hints behave as before.
  - `hints()`: keeps a write hint while its page is cached, whatever the page's age.
  - `forget(route, renderStarted)`: now runs under the state lock and keeps a write hint newer than the render's start. This closes the race where a cron render that began before the post finishes after it.
  - `run()`: treats hinted routes as due even when fresh, and passes each render's start time to `forget`.
- Verification:
  - Passed: `./v3 test --smoke HighTrafficTest HighTrafficPublicationTest`, 453 run, 453 passed.
  - Passed: `./v3 test HighTrafficTest`, 8 run, 8 passed. Two new tests: refresh, queue on a held page lock, hint survives a fresh-looking file, forget by render start, mode off; and cron renders a write-hinted fresh route.
  - Full suite not run.
- Notes:
  - Reuse: `generate` unchanged; the hint queue extended rather than duplicated.
  - A page not yet cached is skipped; its first reader generates it, as today.
- Estimate details (extended mode only): original scope as in Step 3; no revision. The locked `forget` was not in the plan but sits inside this stage's race risk.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Stage 2 - Refresh on every post write

**Original estimate: 0.10**

**Retrospective stage-work assessment: 0.08**

- Changes:
  - `LocalWriteService::withTimedWriteLock` takes `refreshThreadPage`. When set, and the write returned `ok` with a thread ID, it calls the refresh after the write lock is released. Its time goes into `timings` as `high_traffic_refresh`, only when the mode is on.
  - Set on `createThreadForAuthoringMode`, `createReply` (agent replies too), `createPreparedPost`, `createSignedPost` (the `frm.py` and API path) and `createPreparedPostFormat`. Tags, likes, retractions and `setThreadSubjectIfEmpty` are unchanged.
  - `HighTrafficSafety::afterPost(repository, database, threadId)`: builds the cache the same way `beforeWrite` does, returns `inactive` without a state file, and never throws.
  - New `tests/HighTrafficPostRefreshTest.php`, registered in `tests/run.php`.
- Verification:
  - Passed: `./v3 test HighTrafficPostRefreshTest`, 3 run, 3 passed. A reply replaces the cached thread page with one containing the reply; a tag write leaves it alone; a new thread has no cached page and is skipped; a held page lock leaves the post saved and the route queued; mode off leaves the cache and timings untouched.
  - Passed: `./v3 test --smoke HighTrafficTest HighTrafficPostRefreshTest HighTrafficPublicationTest AgentPostingApiTest LocalWriteServiceTitleDirectiveTest`, 463 run, 463 passed.
  - Passed: `./v3 test AgentPostingApiTest LocalWriteServiceTitleDirectiveTest`, 11 run, 11 passed.
  - Not tested directly: the signed, prepared and format paths. They share the same flag and result shape (`status`, `thread_id`), checked by reading each return. The Stage 4 replay covers the signed path through `frm.py`.
  - Full suite not run.
- Notes:
  - One flag on the shared lock wrapper instead of a call in each method, so a new post path needs one argument.
- Estimate details (extended mode only): original scope as in Step 3; no revision. Smaller than expected because every post path already returned `thread_id`.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Stage 3 - Docs and render timing

**Original estimate: 0.05**

**Retrospective stage-work assessment: 0.04**

- Changes:
  - `docs/runbooks/high_traffic_mode.md` (Refresh): a paragraph on post refresh, the queued fallback, and the `high_traffic_refresh` timing.
  - `docs/concepts/static_publication.md`: the high-traffic note now says a new post re-renders its thread page, while the board and `/threads/` follow cron.
  - `docs/reference/v3_cli.md`: one clause in the high-traffic section.
  - `docs/plans/effort_estimation_trial.md`: this cycle's Step 1–3 handoff entries and the Step 4 entry.
- Verification:
  - Passed: `git diff --check`; the new runbook anchor `#refresh` exists.
  - Local render times in the tests: 0.1–0.3 s for the fixture thread.
  - Pending: the maintainer's timing of `./v3 high-traffic refresh /threads/<id>/` on the longest thread on vps22365. If it is over about two seconds, move the render after the response, as Step 3 planned, before merging.
- Notes:
  - The fixture thread is tiny, so local times say little about production.
- Estimate details (extended mode only): original scope as in Step 3; no revision.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Stage 4 - Production replay

**Original estimate: 0.05**

**Retrospective stage-work assessment: pending**

- Changes: none in code.
- Verification:
  - Pending: deploy, then with the mode on the maintainer posts a thread, Claude replies through `frm.py`, and the maintainer reloads on his phone. Expected: the reply shows, and the thread's cached file time is after the reply.
  - Pending: render timing on vps22365 (from Stage 3).
- Notes:
  - Main moved during the cycle (PRs #104 and #111). Merged into the branch as `99c46fca`; the only conflict was the ledger, where both sides added a cycle section and both were kept. Tests rerun after the merge: `./v3 test --smoke HighTrafficTest HighTrafficPostRefreshTest HighTrafficPublicationTest AgentPostingApiTest LocalWriteServiceTitleDirectiveTest`, 463 run, 463 passed.

## Closeout

- Technical status: Stages 1–3 done and committed; Stage 4 pending deploy. Not merged.
- Final diff review: changes stay inside the files Step 2 named, plus `tests/run.php` to register the new test. No dependency changes.
- Retrospective whole-cycle scope assessment: **0.28** (Stages 1–3; Stage 4 pending). Maintainer comfortable-fit judgment: pending.
- Full suite not run.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
