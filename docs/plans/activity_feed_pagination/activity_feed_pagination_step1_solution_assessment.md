> **Feature plan:** [Step 1](./activity_feed_pagination_step1_solution_assessment.md) · [Step 2](./activity_feed_pagination_step2_feature_description.md) · [Step 3](./activity_feed_pagination_step3_development_plan.md) · [Step 4](./activity_feed_pagination_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/activity_feed_pagination`; [ledger](../effort_estimation_trial.md#cycle-v3activity_feed_pagination), standing enrollment P2.

## Original Query
As a user, I want the activity feeds to be paginated.

## Understood Intent

Consolidate duplicated pagination behavior around a reusable component, starting with **standard Activity only**. Use bounded Previous/Next pages; support numbered jumps where useful later. The user explicitly wants **no Forte changes**: retain its append behavior, including Forte Commits. Track other migrations in the [shared pagination checklist](../pagination_consolidation_checklist.md).

## Problem

Independent pagination implementations duplicate navigation, URL state and recovery behavior, while standard Activity provides no access beyond its initial batch.

## Option A — Activity specific pagination

Add bounded Previous/Next pages to standard Activity using Activity-specific navigation and state handling; leave Forte unchanged.

- Pros: smallest immediate change; fixes access to older Activity.
- Cons: adds another bespoke implementation that would need later extraction.

## Option B — Reusable pagination through standard Activity first

Establish a small reusable page-navigation contract and deliver it through standard Activity. Keep queries, ordering and access rules in list-specific services; preserve all existing Forte contracts and behavior.

- Pros: useful first delivery with a reusable boundary; later lists can migrate independently.
- Cons: only one list adopts it initially, so avoid speculative abstraction and validate reuse against the inventory without migrating another consumer.

## Option C — Migrate other lists together

Consolidate standard Activity, QDB, Docs, SQLite viewer, messages and account key history in one cycle, leaving Forte unchanged.

- Pros: reduces duplication across several features immediately.
- Cons: couples unrelated security, snapshot, URL and scroll requirements; substantially expands delivery and verification risk.

## Recommendation

**Option B.** Deliver standard Activity → bounded page navigation → item/source evidence → return and failed-navigation recovery. Introduce reusable page state and accessible Previous/Next controls, preserving existing categories, ordering, permissions and source details. Numbered jumps and expensive totals are not required. Keep list-specific queries separate; create only infrastructure needed by this first consumer.

Verify first/middle/last/empty pages, ties, arrivals/deletions, stale positions, category resets, direct links, refresh, Back/Forward and retry. Check phone/desktop, affected standard themes, keyboard access and expanded long metadata. Preserve per-record likes and security boundaries. Shared-service changes must be additive and regression-tested so Forte's lists, append controls, selection, APIs, preloads and Commits remain unchanged.

## Follow ups and scope

Forte is an explicit exception to this consolidation effort; changing it requires a separate user request. Other migrations stay separate checklist work. RSS remains a recent-items feed. Home/board previews, universal repository-change reporting and other list migrations are outside this delivery. Step 2 defines the reusable boundary and standard Activity behavior.

## Effort snapshots and approval
- Cycle `v3/activity_feed_pagination`, scope v1; starting revision `39be82932fcc36c5017fcf368655ef449bf042da`; reference R1 / protocol P2; Codex, single exposed rater; UTC 2026-10-23.
- Intake: recorded after historical-plan, template, service and controller search exposure; no pristine pre-inspection estimate claimed. Total projection unavailable because the frozen external calibration report/diff sources are unavailable at the recorded location; R1 is retained, with no substitute score.
- Step 1 handoff: total projection remains unavailable for the same reason. No independent rating, measured effort, implementation, completed outcome or maintainer acceptance is claimed.
- Documentation boundary includes this cycle's plans/debug log, later index organization and ledger bookkeeping. Planning writes remain uncommitted.
- Await `Approved Step 1` before Step 2; recommendation may be revised without advancing.

### Revision for shared pagination

- 2026-10-23 UTC; same cycle/baseline/R1/P2 and single exposed rater; scope **v2** supersedes v1's Activity-only Older/Newer recommendation with Option B above. User agreed to the shared direction and requested this revision; Step 1 approval remains pending.
- Prior v1 checkpoints remain historical. Current source context is `e507cb71`, after the merged like-Activity fix. Exposure includes the seven-system inventory, their server/browser paging paths and the tracking checklist; no independent or pristine rating is claimed.
- Revised Step 1 projected total remains **unavailable pending exact frozen R1 reference recovery**. Shared component design and its Activity verification are now included; later migrations are excluded. No measured effort, implementation, completed outcome or acceptance is claimed.

### Revision preserving Forte

- 2026-10-23 UTC; scope **v3** narrows v2 to reusable pagination first used by standard Activity only. The user's explicit instruction leaves all Forte behavior unchanged. Step 1 v2 approval is preserved; this requested narrowing does not approve Step 2 or implementation.
- Same cycle, original baseline `39be8293`, R1/P2 and exposed single rater; prior snapshots remain historical. Revised projected total remains unavailable pending exact frozen-reference recovery. Removing Forte migration reduces delivery scope but retains shared-service compatibility verification; no numeric rating or measured effort is inferred.
