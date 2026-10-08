> **Feature plan:** [Step 1](./agent_response_harness_simplification_step1_solution_assessment.md) · [Step 2](./agent_response_harness_simplification_step2_feature_description.md) · [Step 3](./agent_response_harness_simplification_step3_development_plan.md) · [Step 4](./agent_response_harness_simplification_step4_implementation_summary.md)

# Agent Response Harness Simplification Step 3 Development Plan

## Completion Contract

- **Normal entry:** An approved user selects the existing Request agent response control.
- **End-to-end outcome:** One default named text task is queued, safely fulfilled, and published as the attributed reply-agent response.
- **Required recovery:** Failed, rejected, and duplicate work shows status and posts no extra reply; existing legacy rows and automatic replies remain usable during rollout.
- **Deployment/external verification:** Run focused local tests, the full suite, the queued-worker smoke flow, and one configured-provider task exchange when credentials are available.
- **Release condition:** Default-task and legacy behavior share the durable lifecycle without duplicate publication or loss of structured-analysis safeguards.

## Key Risks

- **High risk: data/rollback:** Legacy and default-task work can collide for one target. Validate repeat and concurrent requests first; retain the existing target/content-hash publication slot and recognize unmarked rows as legacy work.
- **High risk: safety:** Plain-text generation could bypass existing gates. Validate agent-authored, high-risk, ineligible, and provider-failure outcomes before wiring the public request path; retain explicit task preflight policy.
- **High risk: usability:** A compatibility change could alter the current reply unexpectedly. Validate representative output and unchanged card feedback before release; keep the legacy automatic path intact.

## Stage 1

- Goal: Define the default task identity and backward-compatible deduplication boundary.
- Dependencies: Approved Step 2 requirements.
- Expected changes: Add a task descriptor such as `AgentResponseTask::defaultForPost(array $context): array`; record its type and bounded context in existing request metadata. Treat rows without a descriptor as legacy analysis replies, retain the current target/content-hash uniqueness slot for the default task, and plan no database migration.
- Verification approach: Unit-test default and legacy row interpretation, repeated/concurrent requests, existing posted rows, and that one target cannot produce two default/legacy replies.
- Risks or open questions:
  - Impact: Task identity must support a later selector without weakening this slice's duplicate guard.
  - Early warning / validation: Existing-row tests expose an incorrect reuse or second reservation.
  - Mitigation: Limit this slice to one default task and defer independently publishable types to the selector feature.
- Canonical components/API contracts touched: `AgentReplyGenerationStore`, `SqliteAgentReplyGenerationStore`, and existing request-context metadata.

## Stage 2

- Goal: Define the plain-text task contract and default task generator.
- Dependencies: Stage 1 task descriptor.
- Expected changes: Introduce `TextChatProvider::completeTextChat(array $messages, array $options = []): array` and `AgentResponseGenerator::generate(array $task): array`. Add a concise default-task prompt, bounded labeled input, normalization, and a fake/stub generator; no JSON response schema is part of this contract.
- Verification approach: Unit-test labeled input, context bounds, prompt-injection boundaries, normalization, empty output, and task result metadata with a fake provider.
- Risks or open questions:
  - Impact: A loosely defined task can reintroduce hidden mode-specific behavior.
  - Early warning / validation: Contract tests reveal undeclared input or result fields.
  - Mitigation: Limit the contract to one named default task and one reply body.
- Canonical components/API contracts touched: New `TextChatProvider`, new task generator, and existing reply-text normalization.

## Stage 3

- Goal: Adapt configured providers to the plain-text contract.
- Dependencies: Stage 2; shared LLM configuration and exchange recording.
- Expected changes: Have the OpenAI-compatible and Anthropic providers implement `TextChatProvider`, use their native ordinary text-completion format, and record `agent_response_task` exchanges with redacted diagnostics. Reuse the configured provider, model, timeout, and headers; no separate provider configuration is planned.
- Verification approach: Provider-contract tests assert that neither transport sends a response schema, decode exact text, preserve provider metadata, and report transport/provider failures.
- Risks or open questions:
  - Impact: Provider text-completion conventions differ from structured completion conventions.
  - Early warning / validation: Adapter tests assert the native payload and exact completion decoding.
  - Mitigation: Keep provider-specific transport behind the Stage 2 contract and retain exchange diagnostics.
- Canonical components/API contracts touched: OpenAI-compatible and Anthropic providers, `LlmProviderConfig`, and LLM exchange recorder.

## Stage 4

- Goal: Dispatch named tasks through the existing fulfillment and publication lifecycle.
- Dependencies: Stages 1-3; current reply gate and reply-agent publisher.
- Expected changes: Extend `AgentReplyFulfillmentService` to recognize a task descriptor, apply the defined preflight policy, generate its text through `AgentResponseGenerator`, and persist/publish it through the current store and reply writer. Preserve the analysis-suggested-response route for unmarked legacy rows and automatic replies.
- Verification approach: Integration-test default-task publication, safety skips, normalized text, retryable generation/posting failures, and unchanged legacy/automatic fulfillment.
- Risks or open questions:
  - Impact: A task may be claimed while legacy work is already pending or posted.
  - Early warning / validation: Queue-worker tests assert one resulting status and at most one agent post.
  - Mitigation: Reuse the existing reservation and posting transitions rather than introducing a second worker or publisher.
- Canonical components/API contracts touched: `AgentReplyFulfillmentService`, `PostWorkflowService`, `run_agent_reply_requests.php`, and `LocalWriteService`.

## Stage 5

- Goal: Route the existing manual request into the default task without changing its public interaction.
- Dependencies: Stage 4; approved-user authorization and status response contract.
- Expected changes: Have `PostWorkflowService::agentReplyRequestResultForPost(array $post, array $viewerProfile): array` create the default task descriptor while preserving the existing request API, button, card feedback, and response statuses. Do not add the response type selector in this slice.
- Verification approach: API/browser smoke-test approved and unapproved users, requested/in-progress/posted/failed feedback, duplicate clicks, and a legacy row already present.
- Risks or open questions:
  - Impact: Changing the manual route can accidentally alter automatic browser or legacy behavior.
  - Early warning / validation: Regression tests distinguish manual default-task work from automatic and unmarked legacy work.
  - Mitigation: Keep the endpoint payload and UI entry unchanged and route only newly created manual requests to the default task.
- Canonical components/API contracts touched: `PostWorkflowApiController`, `/api/generate_agent_reply`, post/root action controls, and `public/assets/post_analysis.js`.

## Stage 6

- Goal: Make the parallel rollout operable and verify the completion contract.
- Dependencies: Stages 1-5.
- Expected changes: Update the agent-reply provider check and operator reference to exercise a plain-text task exchange; add focused generator, store, fulfillment, API, and worker coverage. No response selector, new queue, or database migration is introduced.
- Verification approach: Run targeted agent-reply/provider tests, `./v3 agent-reply test-local`, the full suite, a temporary-database worker smoke flow, and one live configured-provider task request when available.
- Risks or open questions:
  - Impact: Operator diagnostics could misleadingly describe only the legacy structured call.
  - Early warning / validation: CLI tests and recorded exchange inspection identify the task call type and redact credentials.
  - Mitigation: Document the two concurrent paths and their rollback boundary before enabling the new manual route.
- Canonical components/API contracts touched: `scripts/test_agent_reply_provider.php`, `./v3 agent-reply`, `docs/reference/v3_cli.md`, LLM-exchange diagnostics, and the test runner.
