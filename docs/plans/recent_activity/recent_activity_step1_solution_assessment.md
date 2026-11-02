> **Feature plan:** [Step 1](./recent_activity_step1_solution_assessment.md) · [Step 2](./recent_activity_step2_feature_description.md) · [Step 3](./recent_activity_step3_development_plan.md) · [Step 4](./recent_activity_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/recent_activity; [ledger](../effort_estimation_trial.md#cycle-v3recent_activity), standing P2 enrollment
Extended estimates: enabled
**Cycle estimate: 0.75**

## Original Query

Holy crap, that is a lot of work, lol.

Can you please write Step 1 for the recent activity page?

## Understood Intent

Make new conversation easy to find: a simple, nontechnical page showing new threads and individual replies, including replies to old threads. The [catalog's focused review](../zenmemes_meta_feedback_2026-10-30_summary.md#recent-activity-focused-review) distinguishes the original request from later agent proposals; existing request identity is [PF-20261021-F04](../production_feedback_register.md#pf-20261021-f04).

Problem: Readers cannot readily see what people just said without navigating technical activity records or opening individual threads.

## Option A — Dedicated Recent page (recommended)

**Estimate: 0.75**

Add a plainly discoverable Recent destination showing public threads and replies together, newest first, with author, time, thread context, a short preview and a link to the exact contribution; retain existing Activity and board views.

- Pros: Directly answers “what is new?”; multiple replies remain individually visible; bounded, usable first release.
- Cons: Another destination to maintain; navigation, visibility and static-publication integration drive effort. Performance and compact rendering need early checks.

## Option B — Make existing Activity conversation-first

**Estimate: 0.95**

Make conversation the default at Activity and retain technical records through an explicit alternate view.

- Pros: One familiar destination; existing pagination and serving paths provide a foundation.
- Cons: Changes an established default and affects standard/Forte consumers; preserving technical, security and feed behavior expands regression work. Less isolated than A.

## Option C — Improve the last-touched thread list

**Estimate: 0.35**

Expose active conversations more clearly in the board, showing each thread's latest reply excerpt and direct link.

- Pros: Builds on existing last-activity ordering; smallest navigation change.
- Cons: Five new replies still collapse into one thread entry; useful for finding active discussions, but only partially meets the individual-contribution request.

## Recommendation and boundaries

Choose **A** as one releasable slice: normal navigation → recent contributions → exact post, with native pagination, an honest empty state, invalid-position restart and failed-navigation Reload/Back. Start with all public conversation, humans and agents; exclude bootstrap/system events and honor existing visibility rules. No personal state is needed for first paint.

Reuse: [Activity controller](../../../src/ForumRewrite/Http/ActivityPageController.php) and [pagination contract](../../reference/pagination.md) for navigation/recovery; existing author/time/content helpers from [post cards](../../../templates/partials/post_card.php). The full post card includes composing/agent controls, so a compact presentation may be needed rather than copying it. [ThreadRepository](../../../src/ForumRewrite/ReadModel/ThreadRepository.php) already orders its base board query by last activity; it is not an individual-post feed.

Acceptance must cover old-thread replies, equal timestamps, hidden/removed content, paging during arrivals/deletions, no-JavaScript reading, phone/desktop themes and navigation icons, first paint, bounded read cost, and the ordinary static/live serving path. Reuse current read-model data; query/schema changes still require the project's version/startup/rebuild checks. Replay the reported slow Activity condition if claiming its recovery; this slice does not itself close that report.

Enhancement requests: last-visit/read markers, filters, RSS/API expansion, ranking/self-bump suppression and job-status activity are deferred, unapproved follow-ups from the catalog. No new dependencies or retraction redesign proposed.

Concepts touched: Recent conversation discovery (proposed new); existing post presentation/visibility (used unchanged, inventory entry proposed); [static publication](../../concepts/static_publication.md) (extended route coverage); [derived read model](../../concepts/read_model_rebuild.md) (reuse; query integration may change). Add only touched inventory entries during implementation.

## Study checkpoint and approval

Scope: v1/A; baseline 3a21fab67708c07ef77712790218e12a92c61bcd; R1/P2. 2026-10-30T20:30:16Z, Codex, single exposed rater. Intake after prior catalog/implementation inspection: **0.75**; Step 1 handoff: **0.75 reaffirmed**. Pristine pre-inspection intake unavailable. Inherited feedback-only **0.5** remains separate. R1's three frozen source hashes verified; total includes planning, integration, verification, documentation and correction, not hours or measured labor. A exceeds the 0.60 composer reference through a new serving/query surface; B adds compatibility work; C offers a smaller outcome. Confidence: moderate.

Authorization: Step 1 only; no implementation or commits. Source/document inspection only; runtime and production acceptance unverified. Await **Approved Step 1** before Step 2.

*— Codex · 2026-10-30 UTC*
