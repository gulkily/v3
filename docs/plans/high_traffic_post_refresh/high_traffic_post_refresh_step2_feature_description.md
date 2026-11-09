> **Feature plan:** [Step 1](./high_traffic_post_refresh_step1_solution_assessment.md) · [Step 2](./high_traffic_post_refresh_step2_feature_description.md) · [Step 3](./high_traffic_post_refresh_step3_development_plan.md) · [Step 4](./high_traffic_post_refresh_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/high_traffic_post_refresh`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.30**

**Concepts touched:** [static publication](../../concepts/static_publication.md), changed. Its high-traffic note gains one line: a new post refreshes its thread page right away, while the board and `/threads/` follow the cron refresh. No new concept.

## Problem

In high-traffic mode, a new thread or reply does not appear on its thread page until cron happens to refresh that route. A quiet thread can stay stale for minutes or indefinitely, so a poster and the people they answer cannot see the conversation.

## User stories

- As a poster, I want to land on my thread and see my post, so that I know it went through.
- As someone following a thread, I want a reload to show replies posted since, so that conversations work while the mode is on.
- As the operator, I want the mode to keep protecting the server, so that one busy thread cannot use up rendering capacity.

## Core requirements

- After any write that adds a post to a thread, the thread page is rendered fresh and then swapped over the old cached file. Readers get the old page until the swap and never a missing page.
- This covers every way a post is added: browser posting, signed posting through `frm.py` and the API, and agent replies. A post's format change also changes its thread page and is included.
- The refresh starts only after the read model has the new post, so it cannot cache the old page again.
- If the refresh fails or no generator slot is free, the post still succeeds and is reported as saved. The thread route is queued so the next cron run refreshes it whatever its popularity.
- Only the thread page is refreshed this way. The board, `/threads/`, likes and tags keep the current cron refresh.

## Delivery scope

- Work type: application change.
- Files expected: `LocalWriteService.php` (the step after the write lock is released, where the offline snapshot is queued today), `HighTrafficCache.php` and `HighTrafficRefresh.php` (reuse; at most a small entry point for "refresh this route now"), tests, the static publication concept, the high-traffic operator runbook, and this cycle's plans and ledger entry.
- No feature flag. With the mode off, nothing changes.

## Completion boundary

- Normal entry: with the mode on, a member posts a reply to a thread whose page is cached.
- End-to-end outcome: the redirect after posting shows the reply. A second browser loading the thread shows it too, with no cache hit on the old version.
- Recovery: if the refresh cannot run, the post is saved, the page shows the old version, and the next cron run (within about a minute) fixes it. The operator can still run `./v3 high-traffic refresh /threads/<id>/` by hand.
- Release condition: merged, deployed, and checked on zenmemes.com with the mode on.

## Known user or environment

The maintainer, on zenmemes.com with high-traffic mode on. Today an agent reply to his thread did not appear until he turned the mode off. After release, he posts a thread, an agent replies through `frm.py`, and he reloads the thread on his phone. Evidence: the reply shows without turning the mode off, and the cached file's time is after the reply. If it still lags, he can wait one cron cycle or run the manual refresh above.

## Risks

- **Slower posting.** The poster waits for one page render while the mode is on. Earliest check: time a render of a long thread on the production host during Step 3. Mitigation: if a render is too slow, render after the response is sent and accept that the poster may see the old page once.
- **Race with an older render.** A cron render that started before the post may finish after it and swap in the old page. Earliest check: Step 3 reads how `generate` uses the page lock. Mitigation: when the page lock is busy, the post queues the route, so cron renders it again after the post.
- **Burst of replies to one hot thread.** Each reply tries a render. Mitigation: the page lock waits at most 250 ms, so a reply that finds a render running queues the route instead; the two generator slots cap total work.
- **Rendering under the write lock.** Rendering while holding the write lock would block other posters. Mitigation: refresh only after the lock is released, as the offline snapshot queue does now.

## Shared component inventory

- `HighTrafficCache::generate` with force: reuse as is. It renders into a workspace, checks the mode's state token, and swaps the file atomically.
- `HighTrafficRefresh::hint`: reuse for the fallback queue. It currently refuses routes it considers fresh; the fallback needs it to accept a route a write just changed.
- `HighTrafficRoute::canonical`: reuse to build the thread route from the post's thread ID.
- `LocalWriteService::withTimedWriteLock`: extend its after-write step, which already queues the offline snapshot once a write commits.
- `HighTrafficSafety::beforeWrite` and the `dirty` marker: unchanged. They still cover the board and `/threads/`.

Nothing new is created; the refresh and the queue already exist.

## User flow

1. High-traffic mode is on, and a thread page is cached.
2. A member, or an agent through `frm.py`, posts a reply.
3. The post is saved and the write lock released.
4. The thread page is rendered fresh and swapped in.
5. The poster lands on the thread and sees the reply. Other readers see it on their next load.
6. The board and `/threads/` catch up on the next cron run.

## Success criteria

- With the mode on, a reply appears on its thread page on the first load after posting, for browser, `frm.py` and agent posts.
- No reader gets the busy page because of the refresh.
- With the refresh forced to fail in a test, the post still saves and the next cron run refreshes the thread.
- With the mode off, posting behaves exactly as before.

## Enhancement Requests

- Deploy page "behind" count includes the merge commit. Disposition: separate change, carried from Step 1; still awaiting the maintainer's go-ahead.

## Study Snapshots

2026-11-09T22:45:28Z — Step 2 handoff. Scope v1 unchanged from Step 1 (Option A, Option C fallback); starting revision `a2bcc64d`; R1/P2, hashes not re-verified. Projected total **0.30** reaffirmed: the post write paths share one after-write step, which keeps the change small. Single exposed Claude rater. Not hours or measured labor; no approval to advance.

Step 2 approved on 2026-11-09 ("approved step 2").

Status: Step 2 approved; Step 3 drafted. Planning uncommitted.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
