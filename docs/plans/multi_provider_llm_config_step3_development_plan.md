# Multi Provider LLM Config Step 3 Development Plan

## Stage 1
- Goal: Introduce provider-neutral LLM configuration and shared provider contract.
- Dependencies: Approved Step 2 requirements and existing `PrivateConfig` behavior.
- Expected changes: Add neutral config names for provider, key, base URL, model, timeout, and optional headers; preserve legacy `DEDALUS_*` fallback semantics; define a shared provider call contract for structured chat completions.
- Verification approach: Config-view tests confirm redaction, source precedence, legacy fallback, and neutral names.
- Risks or open questions:
  - Decide exact neutral key names during implementation without breaking existing secrets files.
  - Optional header config must stay private and redacted.
- Canonical components/API contracts touched: `PrivateConfig`, `./v3 private-config`, private config view output, provider diagnostic envelope.

## Stage 2
- Goal: Move Dedalus/OpenAI-compatible HTTP behavior into a reusable adapter.
- Dependencies: Stage 1 provider contract.
- Expected changes: Extract current chat-completions request/response handling into an OpenAI-compatible adapter used by Dedalus, OpenAI, OpenRouter, LiteLLM, and local gateways; keep structured JSON schema requests and response decoding compatible with existing analysis storage.
- Verification approach: Unit tests cover request body, redacted diagnostics, nested provider errors, and decoded structured output without real network calls.
- Risks or open questions:
  - Some OpenAI-compatible providers only partially support `response_format`.
  - Provider metadata should identify both provider family and selected model clearly.
- Canonical components/API contracts touched: `DedalusPostAnalyzer`, `ProviderRequestException`, `PostAnalyzer`, stored `provider`, `provider_model`, and `raw_response` fields.

## Stage 3
- Goal: Add direct Anthropic provider support for post analysis.
- Dependencies: Stage 1 provider contract and existing analysis schema/prompt.
- Expected changes: Add an Anthropic-native adapter that requests structured analysis output and maps response text/request IDs into the existing analysis result contract; preserve provider diagnostics on HTTP, JSON, and schema-decoding failures.
- Verification approach: Unit tests cover Anthropic request shape, successful JSON extraction, request ID capture, and failed response diagnostics.
- Risks or open questions:
  - Anthropic structured-output mechanics differ from OpenAI-compatible `response_format`.
  - Fallback JSON extraction must be strict enough to avoid silently storing malformed analysis.
- Canonical components/API contracts touched: `PostAnalyzer`, analysis response schema, post-analysis prompt usage, provider diagnostic envelope.

## Stage 4
- Goal: Wire provider selection into app analysis and reply fulfillment without changing user workflows.
- Dependencies: Stages 1-3.
- Expected changes: Select the configured provider when creating the post analysis service; keep `stub` mode; keep agent replies generated from stored analysis; ensure missing provider config remains graceful.
- Verification approach: Smoke/API tests cover stub, legacy Dedalus config, OpenAI-compatible config, Anthropic config, missing key behavior, and agent reply request fulfillment.
- Risks or open questions:
  - Existing `DEDALUS_ANALYSIS_MODE` behavior must remain clear during migration.
  - Reply generation has an older direct generator path that should stay compatible or be explicitly non-primary.
- Canonical components/API contracts touched: `Application::postAnalysisService`, `backfill_unicode_risk.php`, agent reply fulfillment service, post analysis API response.

## Stage 5
- Goal: Update operator docs and diagnostics for provider-neutral operation.
- Dependencies: Stages 1-4.
- Expected changes: Update production runbook, private-config help text, and `agent-reply status` wording to describe OpenAI, Anthropic, OpenRouter, Dedalus, LiteLLM, and compatible gateways; keep secrets redacted.
- Verification approach: Local command tests cover help/view/status output; full test suite confirms existing analysis and reply behavior.
- Risks or open questions:
  - Documentation should not imply every gateway/model enforces schemas equally.
  - Keep Dedalus-specific troubleshooting as a compatibility note, not the primary path.
- Canonical components/API contracts touched: `docs/runbooks/production_deploy.md`, `scripts/write_private_config.php`, `scripts/agent_reply_status.php`, `v3` command help.
