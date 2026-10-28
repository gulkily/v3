> **Feature plan:** [Step 1](./activity_load_performance_step1_solution_assessment.md) · [Step 2](./activity_load_performance_step2_feature_description.md) · [Step 3](./activity_load_performance_step3_development_plan.md) · Step 4

Debug mode: enabled
Effort study: railspike-effort / v3/activity_load_performance; [ledger](../effort_estimation_trial.md#cycle-v3activity_load_performance)
Extended estimates: enabled
Cycle estimate: 0.95
Status: Step 1 Option C approved; Step 2 authorized for drafting; no implementation or deployment approval.

## Original Query

When I'm browsing prod zenmemes.com, the activity page can be very slow to load. Please identify possible root causes and write a step 1 for remediation.

## Problem and evidence

Activity can spend seconds waiting for HTML: anonymous production samples returned first bytes at 8.52 and 3.99 seconds, with another request timing out at 25 seconds; the homepage sample was 0.66 seconds.

Both standard and Forte Activity are in scope. Possible contributors include eager evidence work, Forte’s repeated sidebar-count scans and initial selected-item manifest, static-cache bypass, shared startup/lock waits and pagination costs. None is yet isolated as the production root cause. The 255 KB response does not reproduce the previously fixed 88 MB manifest explosion. [Investigation and limitations](./activity_load_performance_investigation.md).

## Option A — Correct static delivery first
**Estimate: 0.45**

Measure route/cache selection, then repair existing artifact delivery and refresh behavior for affected public Activity routes, preserving access rules and recovery.

- Pros: Small operational surface if artifacts or route selection dominate; existing infrastructure.
- Cons: Forte has no equivalent static route in current source; delivery alone cannot cover both views. Deployed configuration is uncertain. Cookie bypass cannot simply be removed for restricted views.

## Option B — Optimize work during requests
**Estimate: 0.85**

Measure both views, then make normal browsing cheap using the existing activity table: remove repeated count scans, bound initial detail work, reuse valid evidence results or resolve them on selection, and fix measured query costs.

- Pros: Helps normal browsing, filtered/paged requests and cache misses; addresses code-supported amplification.
- Cons: Evidence freshness, cursor stability, missing history and detail recovery need careful verification. Shared startup/infrastructure redesign is a separate scope if required.

## Option C — Precompute Activity in the existing read model (recommended)
**Estimate: 0.95**

Compute reusable Activity evidence and category totals during indexing, extending the existing read model only where needed. Both views read stored results through bounded queries; opening or selecting an activity item does not repeat Git traversal, signature verification or category-wide scans.

- Pros: Fits largely append-only history; makes lists and selected details cheap without depending on page caches or moving the delay to selection.
- Cons: New events, retractions/deletions, identity/visibility changes and changed source/signature/key availability must update affected data. Imports, rebuilds and interrupted updates need recovery; index-time cost must stay controlled.

## Recommendation and approval

Choose C, narrowed to database-backed Activity rather than a new page-artifact system. Keep historical event facts distinct from current display/access state. Maintain affected entries and counts through existing indexing/update paths; retire affected cached pages after relevant changes under existing freshness/retraction rules. Do not retain deleted prose in projections or add a new publication-guard system.

The slice covers both initial pages, filters, standard Next/Back and Forte sort/append/selection, including unavailable evidence and rebuild/interruption recovery. Normal browsing must not silently fall back to expensive reconstruction. Full source/commit inspection remains separate. Verify production timing and shared startup independently; precomputation alone does not prove that intermittent host/lock delay is fixed. Step 2 sets measurable latency and freshness targets. Core read-model changes require version/spec updates, prior-version startup recovery tests and rebuild/normal-flow checks. No new dependencies.

Request disposition: Forte slowness added in v2. “Yes, please update step 1” accepts the precompute-on-indexing direction for this revision (v3/C); it does not approve Step 2 or implementation. Await `Approved Step 1` under [Railspike Step 1](../../railspike/docs/workflow/step1_solution_assessment.md).

Study checkpoint: 2026-10-27T22:40:15Z; scope v1/B; baseline 7ef735dd21b3329f18716bf3d5484baab2b2b71b; R1/P2. Codex single exposed rater; pristine intake missing because inspection preceded scoring. First handoff projection 0.70; A/B/C are independent whole-cycle projections, not additive, hours or measured labor. Three frozen R1 hashes verified; standing enrollment applies. No implementation or accepted outcome; maintainer acceptance pending.

Study revision: 2026-10-27T22:57:57Z; scope v2/B includes Forte; projection 0.85 (A/B/C v2: 0.45/0.85/1.25). Earlier v1 estimates 0.40/0.70/1.15 retained above/by this record; increase reflects both added verification scope and count/selected-detail findings. Same baseline/R1/P2 and exposed rater; no implementation.

Study revision: 2026-10-27T23:04:17Z; scope v3/C; 0.95 projected total. C narrowed from v2’s broad projections/artifacts (1.25) to existing-read-model list/detail/count reads with targeted maintenance; A 0.45 and B 0.85 reaffirmed. Existing updater coverage and recovery drive uncertainty; split before implementation if it exceeds a comfortable cycle. Prior scores retained; same baseline/R1/P2/exposed rater, frozen hashes reverified. No implementation or acceptance.

*— Codex · 2026-10-27 UTC*

Approval record: 2026-10-27T23:07:52Z — user: “I'm OK with this. Approved Step 1.” Approves scope v3/C and the estimated storage tradeoff of 0.25–1 KB per event (excluding temporary rebuild/WAL space); this is a planning estimate, not a guaranteed cap. Earlier pending-approval statements above are historical. Proceed to Step 2 only.

*— Codex · 2026-10-27 UTC*
