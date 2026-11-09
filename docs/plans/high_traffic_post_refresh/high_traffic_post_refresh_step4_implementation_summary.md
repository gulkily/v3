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
