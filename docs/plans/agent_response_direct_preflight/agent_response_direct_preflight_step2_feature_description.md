> **Feature plan:** [Step 1](./agent_response_direct_preflight_step1_solution_assessment.md) · [Step 2](./agent_response_direct_preflight_step2_feature_description.md) · Step 3 · Step 4

# Agent Response Direct Preflight Step 2 Feature Description

## Problem

Requested response modes currently wait for full model-backed post analysis even though the resulting legacy suggested reply is not used. This adds avoidable latency, cost, and a second model exchange to a reader’s request.

## User Stories

- As an approved reader, I want my selected response mode to run without waiting for legacy post analysis so that the response arrives promptly.
- As an operator, I want legacy automatic replies and requested response modes to remain separately controllable so that one does not unexpectedly invoke the other.
- As a moderator, I want deterministic request safeguards to remain in force so that invalid, stale, duplicate, and agent-authored targets do not create replies.

## Core Requirements

- A reader-selected response task must not require, start, or wait on full post analysis.
- The request lifecycle must retain authorization, request-feature gating, target/content consistency, duplicate prevention, and agent-loop prevention.
- A deterministic preflight rejection must return the existing clear lifecycle feedback and create no reply or model task.
- Legacy automatic suggested replies remain governed only by their legacy automatic-response flags; their analysis behavior is otherwise unchanged.
- Existing queued selected-task requests fulfill through the direct path after release.

## Completion Boundary

- **Normal entry:** an approved reader selects a response mode from a post card.
- **End-to-end outcome:** the worker validates the target and publishes the selected response after one task-model exchange.
- **Needed recovery:** an invalid, stale, duplicate, agent-authored, disabled, or failed request reports its current lifecycle status without a false success state.
- **Release condition:** no full post-analysis exchange is recorded for a newly requested response unless an independent workflow explicitly invokes analysis.

## Risks

- **Reduced model-derived gating:** requested replies no longer use respondability or moderation recommendations. **Earliest validation:** exercise deterministic rejection cases. **Mitigation:** retain narrow deterministic safeguards and keep the change limited to reader-requested tasks.
- **Path conflation:** legacy automatic work could still run alongside a selected task. **Earliest validation:** inspect exchanges and generated rows with legacy automation disabled. **Mitigation:** preserve separate flags and verify each path independently.
- **Historical queued work:** pending rows may have been created under the old prerequisite. **Earliest validation:** fulfill a stored selected task after deployment. **Mitigation:** retain the task and lifecycle record contracts.

## Shared Component Inventory

- **Response-mode chooser and request API:** reuse their current authorization, selection, and lifecycle behavior; no new entry point.
- **Request fulfillment worker:** extend the canonical fulfillment path to use deterministic preflight for selected tasks.
- **Post analysis and legacy automatic-reply path:** retain as the canonical mechanism for legacy automatic replies and moderation-related analysis; do not invoke it from selected-task fulfillment.
- **Generated-response store and feedback surfaces:** reuse existing status, duplicate, failure, and publication rendering.

## User Flow

1. An approved reader selects a named response mode.
2. The system queues the selected task after existing request validation.
3. The worker applies deterministic preflight checks.
4. The worker makes one selected-task model request and publishes its reply, or returns a clear rejection/failure status.

## Success Criteria

- A new selected-mode request produces one `agent_response_task` exchange and no prerequisite `post_analysis` exchange.
- Disabled legacy automatic replies do not affect reader-requested tasks.
- Deterministic rejection, duplicate, stale-target, and agent-loop cases create no reply.
- Selected replies continue to publish and link from their originating post.
