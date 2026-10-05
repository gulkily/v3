> **Feature plan:** [Step 1](./agent_response_harness_simplification_step1_solution_assessment.md) · [Step 2](./agent_response_harness_simplification_step2_feature_description.md) · [Step 3](./agent_response_harness_simplification_step3_development_plan.md) · [Step 4](./agent_response_harness_simplification_step4_implementation_summary.md)

# Agent Response Harness Simplification Step 1 Solution Assessment

## Original Query

Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md for simplifying the agent response harness in preparation for the response type selector. It makes sense to do this first, right?

## Problem Statement

The current requested-reply path derives public prose from a large structured post analysis, which makes distinct response types unnecessarily coupled to classification output.

## Option A: Add response types to the current structured analysis contract

Pros:
- Retains the current model and reply path.
- Requires no new response-generation boundary.

Cons:
- Expands an already broad schema for each added type.
- Continues to require analysis fields that a requested text response does not need.

## Option B: Separate plain-text response tasks from structured post analysis

Pros:
- Gives each named response type a small, focused text-generation contract.
- Lets the selector submit a type and bounded labeled context to one shared harness.
- Retains durable request state, publication, authorization, and safety controls.

Cons:
- Requires a compatibility decision for the existing generic reply behavior.
- Introduces a distinct task-generation lifecycle to operate and test.

## Option C: Replace all model-facing structured contracts with delimited text

Pros:
- Maximally simplifies model input and output conventions.
- Removes schema maintenance across the subsystem.

Cons:
- Weakens structured classification data used by moderation, gating, and related-content decisions.
- Is a broad migration rather than a safe prerequisite for the selector.

## Option D: Build the response type selector before simplifying the harness

Pros:
- Delivers visible selection UI sooner.
- Defers a potentially disruptive internal change.

Cons:
- Forces the selector to encode current analysis-specific behavior.
- Makes the later simplification more expensive and risks duplicate orchestration paths.

## Recommendation

Recommend Option B.

Brief justification:
- Yes, this should happen first as a bounded vertical slice: preserve the existing durable request and publication lifecycle, but make requested response generation a named task that returns normalized text rather than a full analysis object.
- Keep structured post analysis where its machine-readable moderation, eligibility, and discovery signals are genuinely consumed; do not make it the response-type contract.
- Step 2 should define the shared task boundary, the existing generic reply's compatibility behavior, task-specific safety checks, context limits, and the first selector consumer.
