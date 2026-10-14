> **Feature plan:** [Step 1](./scripture_oriented_analysis_step1_solution_assessment.md) · [Step 2](./scripture_oriented_analysis_step2_feature_description.md) · [Step 3](./scripture_oriented_analysis_step3_development_plan.md) · [Step 4](./scripture_oriented_analysis_step4_implementation_summary.md)

Debug mode: disabled

# Scripture-Oriented Analysis Step 3 Development Plan

## Completion Contract

- Entry and outcome: An eligible reader selects “Scripture-oriented analysis” on a thread or standalone post page; the existing request flow publishes the labeled reflection under the intended post.
- Recovery: Preserve eligibility, duplicate suppression, visible failure feedback, and existing retry behavior, retaining the selected mode throughout.
- Prompt: Keep editable instructions in `prompts/agent_response_scripture_oriented_analysis.txt`. Start with the approved Step 2 behavior, including the disclosed Christian biblical default; wording can be refined in that file.
- Release: Pass focused automated checks, desktop/narrow-screen chooser checks, and representative model-output review. Use isolated test content for publication checks. If provider or browser verification is unavailable, record the outstanding check and do not claim release readiness. No schema migration or new external service is expected.

## Key Risks

- **High risk:** The response could adopt an unwanted tradition or imply the requester's beliefs. Step 2 approval settles the initial default; inspect the prompt before wiring and review explicit/unspecified-tradition outputs before release.
- Recalled scripture may be inaccurate. Require references, quotation/interpretation distinctions, and uncertainty handling in the prompt; review representative outputs against source passages. Mock-provider tests establish integration, not scriptural accuracy.
- Prompt edits could make tests brittle. Test routing and loading against the prompt file's contents rather than freezing its prose; review behavior separately after substantive prompt changes.

## Stage 1

- Goal: Deliver the new mode through the existing chooser-to-reply path (about one hour).
- Dependencies: Approved Step 3; create the feature branch, organize approved plans and the planning index, and commit planning artifacts before implementation.
- Expected changes: Register stable type `scripture_oriented_analysis` and its label in `AgentResponseTask`; map it to the dedicated text file in `AgentResponseGenerator`; write the initial prompt. Extend task/generator coverage for catalog membership, stored-mode preservation, and correct prompt loading. No function-signature or database changes are planned.
- Verification approach: Run `./v3 test AgentResponseTaskTest AgentResponseGeneratorTest`; lint changed PHP. Confirm the shared catalog exposes the choice and the generator receives both the selected prompt and bounded forum context.
- Risks or open questions: A missing mapping can silently select the generic prompt; validate exact file routing and preserve untrusted-content boundaries before Stage 2.
- Canonical components/API contracts touched: `AgentResponseTask`, `AgentResponseGenerator`, existing prompt loader, shared chooser catalog, and selected response-mode contract; reuse request and fulfillment services.

## Stage 2

- Goal: Verify normal use, recovery, and output quality (about one hour).
- Dependencies: Stage 1 checks pass; record its results and commit before starting this stage.
- Expected changes: Extend existing API/UI smoke coverage for the new mode on both page surfaces, retained selection through queued publication, duplicate handling, and failure feedback. Refine the prompt text if output review exposes gaps; record verification in the implementation summary.
- Verification approach: Run `./v3 test AgentResponseTaskTest AgentResponseGeneratorTest AgentReplyGenerationTest WriteApiSmokeTest`. Check keyboard and narrow-screen chooser use. Review model outputs for explicit tradition, no tradition, supplied quotation, uncertain reference, and no useful connection; verify cited passages against authoritative texts.
- Risks or open questions: Provider access may block output review; establish availability early and report incomplete external checks explicitly. Ensure the added choice stays visible and failures retain the existing recovery path.
- Canonical components/API contracts touched: Existing request/status API, queued fulfillment, thread/post page catalog, root/reply cards, shared dropdown, and normal published-reply rendering; extend shared components only if verification reveals a gap.
