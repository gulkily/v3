> **Feature plan:** [Step 1](./agent_response_harness_simplification_step1_solution_assessment.md) · [Step 2](./agent_response_harness_simplification_step2_feature_description.md) · [Step 3](./agent_response_harness_simplification_step3_development_plan.md) · [Step 4](./agent_response_harness_simplification_step4_implementation_summary.md)

# Agent Response Harness Simplification Step 2 Feature Description

## Problem

Requested agent replies are currently extracted from a broad structured analysis, rather than generated as focused response tasks. The response-type selector needs a reusable task harness that can generate one text response without expanding that analysis contract.

## User Stories

- As an approved reader, I want the current request control to continue producing a useful agent reply so that the harness change does not disrupt me.
- As an operator, I want requested-response generation separate from post analysis so that each future response type has a focused contract.
- As an operator, I want the old and new paths to coexist safely so that rollout and recovery do not create duplicate replies.

## Core Requirements

- The existing approved-user request control must create one default named text-response task; its public result must not be extracted from structured analysis output.
- A task must receive its type, target content, and bounded labeled context, and produce one normalized reply body or a clear non-public outcome.
- Reuse authorization, loop prevention, idempotency, queueing, status, reply-agent publication, and requester feedback; task identity must prevent a legacy and new request from colliding or publishing duplicates.
- Preserve structured post analysis and its moderation, eligibility, and discovery consumers; it may inform task safety, but is not the task-response format.
- Keep legacy request and automatic-reply behavior operational during the parallel rollout; failures must be visible and retryable without silently posting a fallback reply.

## Completion Boundary

- **Normal entry:** an approved user selects the existing Request agent response control.
- **End-to-end outcome:** the default task is queued, safely fulfilled by the simplified harness, and published as the normal attributed reply-agent response.
- **Recovery:** a failed, rejected, or duplicate task reports its existing-style status and creates no extra reply; legacy behavior remains available during rollout.
- **Release condition:** the default task and legacy path coexist without regression, duplicate publication, or loss of analysis-dependent safeguards.

## Risks

- **Compatibility regression:** current requests may change quality or availability; validate representative request outcomes early and retain the legacy path as rollback.
- **Safety-policy gap:** decoupling prose from analysis could bypass a gate; validate rejected, high-risk, and agent-authored targets early and define the task safety boundary before Stage 3.
- **Identity collision:** old and new work on the same target may deduplicate incorrectly or double-publish; validate concurrent/repeated requests early and scope idempotency to the response task.

## Shared Component Inventory

- **Post-card and thread-root request controls:** reuse as the temporary canonical entry for the default task; defer the selector UI.
- **Agent-response request API:** extend as the canonical request surface while preserving its current caller contract.
- **Generated-response queue, status, and feedback surfaces:** extend as the single durable lifecycle; do not add a parallel queue or status UI.
- **Reply-agent publication and post rendering:** reuse for the final attributed reply.
- **Post analysis, its disclosures, and automatic reply work:** retain as separate structured-analysis consumers and legacy behavior during rollout.

## Simple User Flow

1. An approved user requests an agent response from an eligible post.
2. The system records one default named text task and shows its queued status.
3. Shared safety checks allow, reject, or defer the task.
4. The simplified harness produces a normalized reply and the existing publisher posts it as reply-agent.
5. The card shows the published, skipped, or failed outcome; a failed task can be retried without a duplicate reply.

## Success Criteria

- A manual request produces a reply from the default text task rather than `suggested_response` in the structured analysis.
- Existing authorization, loop prevention, status, and visible reply-agent attribution remain intact.
- Repeated and concurrent legacy/new-path requests yield at most one published reply for the same task identity.
- Structured analysis continues to provide its current machine-readable outcomes independently of the task's reply text.
- A failed or ineligible task posts nothing and gives the requester an actionable status.
