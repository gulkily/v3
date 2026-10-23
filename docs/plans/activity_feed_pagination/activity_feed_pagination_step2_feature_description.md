> **Feature plan:** [Step 1](./activity_feed_pagination_step1_solution_assessment.md) · [Step 2](./activity_feed_pagination_step2_feature_description.md) · [Step 3](./activity_feed_pagination_step3_development_plan.md) · [Step 4](./activity_feed_pagination_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/activity_feed_pagination`; [ledger](../effort_estimation_trial.md#cycle-v3activity_feed_pagination), standing enrollment P2; scope v3.

## Problem

Standard Activity stops at its first batch. A reusable pagination boundary can provide access to older entries and reduce duplication in later migrations, while Forte retains its existing append behavior.

## User stories

- As a standard Activity reader, I want bounded Previous/Next pages so that I can reach older entries and return to my place.
- As a maintainer, I want reusable pagination controls/state so that later lists can adopt them independently.

## Core requirements

- **Reusable contract:** bounded items, available Previous/Next destinations and navigation state; optional totals/numbered jumps. Separate accessible navigation and URL handling from list-specific queries, ordering and permissions. Standard Activity is the only adopting consumer; do not build unused extension features.
- **Navigation:** Previous moves toward the start of the current ordering; Next moves away. Hide unavailable directions. URLs preserve category and page position; category changes reset position. Links work without JavaScript and support refresh, sharing and Back/Forward. Retain standard Activity's current date-descending order; no new sort controls.
- **Consistency:** at most 100 items per page. Stable ordering survives ties/rebuilds; arrivals do not shift unchanged items across an existing forward traversal. First-page refresh includes arrivals. Deletions/withdrawals/access changes apply immediately; no historical snapshot guarantee. Any displayed totals distinguish page count from accessible total.
- **Recovery:** malformed/context-mismatched positions offer a first-page restart; empty/deleted pages retain return navigation. A failed request has a usable retry/return path without losing navigation state. Native links suffice; enhanced navigation is not required. If added, it preserves the current page until success, offers retry and discards abandoned responses. History navigation rechecks current access.
- **Evidence and serving:** preserve membership, per-record likes, source/signature/commit details, security notices and authorization. RSS stays recent-only. Position-bearing requests cannot receive an unrelated static first page; unavailable offline destinations provide an online recovery path. Keep protected responses isolated.

## Delivery and completion boundary

**Application change:** reusable pagination first used by `/activity/`, including existing categories, ordering and evidence. **No Forte changes:** retain its append behavior, Commits, controls, selection, API contracts and preloads. Deliver developer guidance in `docs/reference/pagination.md`, linked from `CONTRIBUTING.md`, covering reuse, list-specific adapters and required verification with a worked standard Activity example. Other migrations/numbered controls remain in the [checklist](../pagination_consolidation_checklist.md); no production deployment.

Ordinary entry is standard Activity → Next beyond its initial batch → inspect evidence → Previous/Back. With over 100 eligible items, the reader reaches previously inaccessible entries and can retry/restart without editing URLs. Release requires UI/API/rebuild checks, real browser phone/desktop/theme verification, explicit serving limits and Forte compatibility checks.

## Risks

- **Speculative abstraction:** before Step 3, compare the proposed reusable boundary with other lists' needs; implement only what standard Activity uses.
- **Skipped entries:** inspect ties, arrivals, deletion and rebuild positions early; define stable traversal/restart semantics.
- **Disclosure/stale serving:** inspect access transitions and static routing early; preserve authorization and protected-cache isolation.
- **Forte regression:** inspect shared-service callers before Step 3; add compatible standard-page behavior without altering existing signatures/results and verify Forte append/detail/Commits flows.

## Shared component inventory

Extend standard `ActivityPageController`/`activity.php` and reuse `ActivityService` membership, ordering and evidence. Add reusable page controls/state; existing QDB numbered links do not cover Activity's position/recovery requirements. Extend shared services additively if needed. Forte controller, paging/detail APIs, partials and reader are compatibility surfaces only, with no planned changes. Review `FrontController`, static publication/invalidation and offline routing for standard-page positions. Preserve RSS, instance previews and all other consumers.

## Simple user flow

1. Open standard Activity and select a category.
2. Follow Next/Previous and inspect an entry's evidence.
3. Refresh, share the URL or use Back/Forward to revisit a position.
4. Retry a failed request or restart from the first page for an invalid position.

## Success criteria

On an unchanged dataset exceeding two pages, traverse all eligible action keys once and return in order. Verify arrivals, ties, deletion, rebuild, boundaries, direct links, category resets, history, expanded evidence, keyboard access, long-value overflow, retry and protected-cache isolation. Demonstrate standard navigation without JavaScript and separable reusable controls/state; verify Forte's existing append, detail, selection and Commits behavior remains unchanged. Record shared-contract/list-specific checks and verification limits.

## Study checkpoint and approval

2026-10-23 UTC; Step 1 scope v2 approved. Same stable cycle, original baseline `39be8293`, R1/P2 and single exposed Codex rater; current inspected checkout `e5f4bce6`. Exposure includes the approved assessment/checklist and existing services, controllers, preload and static routing. Projected total remains **unavailable pending exact frozen calibration report/diff recovery**; prior v1/v2 snapshots remain intact. No independent rating, measured effort, implementation or accepted outcome. Original v2 handoff awaited Step 2 approval; see the revision below for current scope.

### Revision preserving Forte

2026-10-23 UTC; scope v3 follows the user's explicit no-Forte-changes instruction and the aligned Step 1 revision. Above requirements supersede the original Step 2 v2 proposal; its handoff is preserved in the ledger/debug history. Same cycle/baseline/R1/P2 and exposed rater. Projected total remains unavailable pending exact frozen-reference recovery; no implementation or measured effort. Await **Approved Step 2** for this narrowed scope.

### Developer guidance clarification

The user explicitly requested project pagination implementation guidance at completion. This makes the existing documentation deliverable concrete; scope v3 and the standard-only/Forte-unchanged boundary remain unchanged. Step 2 v3 approval is preserved. Document the delivered API and example during implementation rather than presenting planned interfaces as available now.
