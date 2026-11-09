> **Feature plan:** [Step 1](./high_traffic_post_refresh_step1_solution_assessment.md) · [Step 2](./high_traffic_post_refresh_step2_feature_description.md) · [Step 3](./high_traffic_post_refresh_step3_development_plan.md) · [Step 4](./high_traffic_post_refresh_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/high_traffic_post_refresh`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.30

## Original Query

I didn't see your reply until I turned off high traffic mode. Is there something we can do to make high traffic mode not retain or in-place update the generated page if there's an update to it? Should be pretty easy, since we know which thread we're replying to and all other affected pages?

Please write Step 1, and "replace in place" means generate and then replace, right?

I think some routes are less important to keep exactly up to date, but this thread is a good example of ones that are important.

## Understood Intent

The maintainer posted a question thread, and Claude replied about two minutes later. With high-traffic mode on, the thread page kept showing only the question until the mode was turned off. A new post should appear on its own thread page right away, even in high-traffic mode. The board (`/`) and `/threads/` may stay a little behind, as they do today. "Replace in place" means render a new copy first, then swap it over the old file, so readers get the old page until the new one is ready.

## Problem Statement

In high-traffic mode, a new thread or reply leaves its thread page stale until the per-minute refresh happens to pick that route, which may take minutes or never happen.

## How it works today

`HighTrafficSafety::beforeWrite` runs before each canonical write. For an ordinary post it only drops one global `dirty` marker. `FrontController` keeps serving the cached file whatever its age. `HighTrafficRefresh::run` rebuilds a route only when it ranks as popular in the access log or a reader's browser sent the 60-second hint from `high_traffic.js`. A thread nobody else is reading can stay stale indefinitely.

## Concepts touched

- All options: [static publication](../../concepts/static_publication.md) (changed: the high-traffic refresh path gains a write-triggered refresh). No new concept proposed.

## Solution Options

### Option A: Re-render the thread page after each post; leave other routes to cron

**Estimate: 0.30**

After a thread or reply is written and the read model has it, render that thread's page and swap it in. This reuses `HighTrafficCache::generate` with force, which already renders to a workspace and replaces the file atomically. The poster waits for the render, so their redirect shows their own post. `/` and `/threads/` keep today's path through the `dirty` marker and cron. If the render fails or no generator slot is free, the post still succeeds and the route gets a hint so the next cron run takes it regardless of popularity.

- Pros: the important page is current before anyone is sent to it; readers never see a missing page; reuses the existing generator, locks and capacity limits.
- Cons: posting takes longer by one page render while the mode is on; a burst of replies to one hot thread renders it once per reply.
- Driver: running the render after the read model is updated, outside the write lock. Uncertainty: render time for a long thread on the production host.

### Option B: Delete the thread's cached page after each post

**Estimate: 0.20**

After the write, remove the thread's cached file. The next reader's request misses the cache and renders a fresh page.

- Pros: smallest change; no extra wait for the poster.
- Cons: a miss under load goes through two generator slots and a 250 ms wait, so readers can get the 503 busy page; that is worst exactly on a hot thread, which is when the mode is on.
- Driver: the same ordering after the read model update.

### Option C: Queue the thread page for the next cron run

**Estimate: 0.15**

After the write, drop a hint for the thread route so cron refreshes it within a minute, regardless of popularity.

- Pros: no work on the write path; uses the existing hint queue.
- Cons: the poster's redirect still shows the old page; up to a minute or two of delay remains.
- Driver: making cron honor hints from writes even when the page counts as fresh.

## Recommendation

**Option A**, with Option C as its fallback when the render can't run. Only thread pages get the immediate refresh. The board and `/threads/` stay stale-tolerant, as the maintainer suggested.

Step 2 should settle two edge cases: posts that touch more than one thread page (thread moves or merges, if any exist) and whether edits or retractions of a post use the same refresh.

**Vertical-slice viability:** with the mode on, post a reply to a cached thread, follow the redirect and see the reply. A second browser loading the thread sees it too. `/` may still show the old reply count until the next cron run.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Enhancement Requests

- Deploy page: "behind" counts the merge commit while "incoming" skips it ([thread](https://zenmemes.com/threads/thread-20261109222616-7edadd02)). Disposition: separate small change, not part of this cycle; awaiting the maintainer's go-ahead for a PR.

## Study Snapshots

2026-11-09T22:40:51Z; scope v1; starting revision `a2bcc64d3c0d4f007fef27f79b1b3acb7f58c0e2`; reference R1 (hashes not re-verified). Claude, single exposed rater; the high-traffic code was read before rating, so no pristine intake exists.

- Intake and Step 1 handoff: **0.30 projected total** for Option A with the Option C fallback. Option estimates A 0.30, B 0.20, C 0.15, non-additive. Not hours or measured labor; no approval to advance.

Step 1 approved on 2026-11-09 ("approved step 1"): Option A with the Option C fallback.

Status: Step 1 approved; Step 2 drafted. Planning uncommitted.
