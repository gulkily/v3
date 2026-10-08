> **Feature plan:** [Step 1](./agent_response_direct_preflight_step1_solution_assessment.md) · [Step 2](./agent_response_direct_preflight_step2_feature_description.md) · [Step 3](./agent_response_direct_preflight_step3_development_plan.md) · Step 4

# Agent Response Direct Preflight Step 3 Development Plan

## Completion Contract

- Normal entry: an approved reader selects a response mode from a post card.
- End-to-end outcome: the worker applies deterministic preflight and publishes the selected response after one task-model exchange.
- Required recovery: disabled, duplicate, stale, invalid, agent-authored, and provider-failed requests retain their current clear lifecycle result without publishing a reply.
- Deployment/external verification: run focused request/worker tests and inspect recorded exchanges for a fresh selected-mode request.
- Release condition: selected tasks do not invoke full post analysis; legacy automatic replies remain independently gated.

## Key Risks

- **High risk: safety regression.** Impact: requested tasks could bypass a necessary deterministic rejection. Early validation: test every existing non-model guard. Mitigation: define and exercise one shared preflight before task generation.
- **High risk: response-store collision.** Impact: direct tasks could overwrite or misclassify legacy rows. Early validation: test pending, complete, posted, and duplicate rows. Mitigation: preserve the current target/content-hash lifecycle and derive a stable direct-task provenance value.
- **High risk: hidden analysis call.** Impact: latency remains despite the new path. Early validation: assert task fulfillment never invokes post analysis and inspect the exchange record. Mitigation: make direct-task fulfillment a separate branch before analysis lookup.

## Stage 1 - Deterministic direct-task preflight

- Goal: Establish one narrow non-model validation path for stored response tasks.
- Dependencies: Approved Step 2; current task identity and target/context contracts.
- Expected changes: Identify supported stored tasks before analysis; validate target/content match and existing agent-loop/lifecycle conditions; return current skip statuses without model work.
- Verification approach: Unit/worker coverage for valid, stale, agent-authored, invalid, and duplicate task rows; assert no analysis service invocation for preflight rejection.
- Risks or open questions:
  - Impact: a legacy unmarked request may take the new path accidentally.
  - Early warning / validation: fixture an unmarked historical request.
  - Mitigation: keep historical unmarked requests on the legacy fulfillment branch.
- Canonical components/API contracts touched: `AgentReplyFulfillmentService`; `AgentResponseTask`; generated-response lifecycle contract.

## Stage 2 - Direct selected-task generation and publication

- Goal: Fulfill valid stored tasks without analysis while retaining canonical storage and publishing.
- Dependencies: Stage 1 preflight and existing task generator.
- Expected changes: Add a direct-task publication path with stable task-derived provenance in place of an analysis-derived value; reuse reservation, completion, posting, and feedback contracts.
- Verification approach: End-to-end selected-mode request/claim/publish test asserts exactly one task exchange and no `post_analysis`; verify durable response intent and idempotent repeat fulfillment.
- Risks or open questions:
  - Impact: existing rows may be treated as in progress or lose their response label.
  - Early warning / validation: exercise requested, complete, posted, and failed records.
  - Mitigation: retain the existing target/content-hash keys and mode-label lookup.
- Canonical components/API contracts touched: `AgentReplyFulfillmentService`; `SqliteAgentReplyGenerationStore`; `AgentResponseGenerator`; request worker.

## Stage 3 - Legacy isolation and operational verification

- Goal: Confirm selected tasks and legacy automatic replies operate independently.
- Dependencies: Stages 1–2; existing private feature flags.
- Expected changes: Preserve the legacy automatic path only for unmarked historical work and its dedicated flags; document the two operational controls and direct-task exchange expectation.
- Verification approach: Focused feature-flag/API/worker tests with legacy automation off and task requests on; inspect a recorded fresh exchange; run syntax checks.
- Risks or open questions:
  - Impact: disabling legacy automation could disable reader requests.
  - Early warning / validation: request and fulfill a selected task with the legacy flag off.
  - Mitigation: retain `AGENT_RESPONSE_REQUESTS_ENABLED` as the sole reader-request feature gate.
- Canonical components/API contracts touched: `PostWorkflowService`; `PostWorkflowApiController`; feature-flag registry; CLI/operator documentation.
