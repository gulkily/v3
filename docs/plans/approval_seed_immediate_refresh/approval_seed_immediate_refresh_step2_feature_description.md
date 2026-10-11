> **Feature plan:** [Step 1](./approval_seed_immediate_refresh_step1_solution_assessment.md) · [Step 2](./approval_seed_immediate_refresh_step2_feature_description.md) · [Step 3](./approval_seed_immediate_refresh_step3_development_plan.md) · [Step 4](./approval_seed_immediate_refresh_step4_implementation_summary.md)

## Problem

`./v3 approve` and `./v3 approval seed` currently require a full read-model rebuild before reporting success. Operators need seeded approval to take effect immediately without that routine rebuild cost.

## User Stories

- As an operator, I want either command to apply seed approval before returning success so that no follow-up rebuild or background wait is needed.
- As a newly approved member, I want my next applicable request to reflect my approval so that I can use existing member capabilities.
- As an operator, I want failures to distinguish persisted approval from incomplete refresh so that I can recover safely.

## Core Requirements

- Both command spellings retain existing arguments, repository selection, identity normalization, and canonical seed semantics, including root attribution; seeding creates no approval reply.
- On a healthy, current read model, apply approval synchronously without a full rebuild. Success means the next applicable read reflects the result; already-open pages need no push update.
- Match fresh-rebuild behavior for direct and transitive approvals, attribution-only changes, approval-sensitive access, activity author state, and affected scores, including seeding an already-approved identity.
- Refresh affected served surfaces, including cached/static output, before success; no separate publication or rebuild may be required for visibility.
- Missing, stale, or incompatible read models and refresh failures produce actionable failure reporting without automatic full rebuild. Disclose whether the seed persisted, prevent false success, and support safe retry after repair without duplicate records or changed seed meaning.

## Delivery Scope and Completion Boundary

**Work type:** Application change. Deliver the operator flow from either existing CLI entry through persistence, immediate approval effects, and failure recovery. Reuse shared approval behavior; exclude new approval policies, command redesign, background rebuilding, and unrelated write-path changes. Release only after both commands satisfy parity, visibility, and recovery checks; full rebuild remains an exceptional repair tool.

## Risks

- **Incomplete propagation:** Access, attribution, or scores could disagree. Validate direct, transitive, and attribution-only examples before implementation; require fresh-rebuild parity across all affected results.
- **Stale presentation:** Cached pages could hide a successful approval. Inventory affected dynamic/static readers during planning; retain shared rendering and require next-request checks with cached output present.
- **Partial failure or concurrent writes:** Persisted seeds could become unretryable or state could diverge. Resolve failure boundaries and existing writer coordination in Step 3 before dependent work; require explicit persistence reporting and retry/concurrency verification.

## Shared Component Inventory

- **CLI:** Extend the shared seed operation behind both command spellings and reuse approval derivation; retain existing user-to-user approval behavior.
- **Identity presentation:** Reuse profile/username and Forte profile/detail views, approved/pending user directories and their APIs, and approval controls; extend shared freshness handling where needed.
- **Content presentation:** Reuse board/thread/post and bootstrap views, activity/instance views, score displays, text/content APIs, and SQLite-backed query views; keep their approval-derived results consistent.
- **Access and operations:** Reuse authentication/lobby/member gates, approval/invitation and posting/tagging controls, private-message access, tools, and codebase-state views wherever they consume approval state.
- **Publication:** Extend existing artifact invalidation for affected served pages/releases. No new UI, API, or parallel renderer is needed.

## Simple User Flow

1. Operator runs either seed command for an identity.
2. The command persists the seed and synchronously applies its approval effects.
3. On success, subsequent applicable requests use the updated state; on failure, the operator receives persistence status and repair/retry guidance.

## Success Criteria

- Both commands perform zero full rebuilds on the healthy path and return success only after refreshed state is visible on the next request.
- Direct, transitive, attribution-only, activity, and score results match a fresh rebuild, including cached-page scenarios and existing member access rules.
- Unavailable/stale/incompatible state and injected refresh failures return failure with accurate persistence status; retry after repair succeeds without duplicate canonical writes.
- Representative before/after timings document reduced command latency; no background completion interval is introduced.
