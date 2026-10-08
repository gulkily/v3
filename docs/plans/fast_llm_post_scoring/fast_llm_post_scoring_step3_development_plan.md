# Fast LLM Post Scoring Step 3 Development Plan

## Stage 1
- Goal: Add independently selectable, disableable fast-scoring configuration without changing full-analysis configuration.
- Dependencies: Approved Step 2 requirements.
- Expected changes: Add a fast-scoring config value object and private-config example/view entries for provider, model, timeout, and rubric/prompt; extract shared provider creation if needed.
- Verification approach: Unit-test defaults, overrides, disabled state, and that full-analysis values remain unchanged.
- Risks or open questions:
  - The configured rubric must explicitly define the probability's endpoints.
- Canonical components/API contracts touched: `LlmProviderConfig` configuration conventions; private-config example and view.

## Stage 2
- Goal: Define the focused score result and the compact root/reply input boundary.
- Dependencies: Stage 1 configuration contract.
- Expected changes: Add `FastScoreContextFactory::forPost(array $post): array` and a result contract containing status, nullable probability, source, and signals; include target text only for roots and target text plus bounded parent/root context for replies.
- Verification approach: Unit-test root and reply contexts, truncation limits, and exclusion of thread-wide analysis data.
- Risks or open questions:
  - Parent and root preview limits must preserve useful context without weakening the small-request goal.
- Canonical components/API contracts touched: `PostWorkflowService::postAnalysisContext()` as the established context-boundary reference; new internal fast-score contract.

## Stage 3
- Goal: Produce and validate one probability from the selected lesser model.
- Dependencies: Stages 1–2; existing structured-chat provider abstraction.
- Expected changes: Add `FastPostScorer::score(array $context): array`, a short rubric prompt, a single-probability response schema, range/type validation, and `fast_post_score` exchange metadata.
- Verification approach: Use a fake provider to verify the compact payload, one-value schema, valid boundary values, and explicit invalid/provider-failure outcomes.
- Risks or open questions:
  - Providers may return syntactically valid but semantically poor probabilities; calibration remains outside the scorer contract.
- Canonical components/API contracts touched: `StructuredChatProvider`, `LlmExchangeRecorder`, and the private LLM-exchanges audit surface.

## Stage 4
- Goal: Add a transparent deterministic companion without claiming it is model output.
- Dependencies: Stage 2 result contract.
- Expected changes: Add `DeterministicFastScoreEvaluator::evaluate(array $context): ?array` for objective hard exclusions and named signals; route excluded posts without an LLM call and reserve rubric-specific heuristic probabilities for later calibration.
- Verification approach: Unit-test each exclusion/signal and assert that its source is `heuristic`, while model outcomes remain `llm`.
- Risks or open questions:
  - No generic heuristic probability is valid until the first scoring rubric is selected and evaluated.
- Canonical components/API contracts touched: New internal fast-score result contract; no reader-facing score display.

## Stage 5
- Goal: Compose configuration, compact context, deterministic routing, and LLM scoring for internal callers.
- Dependencies: Stages 1–4; existing post lookup conventions.
- Expected changes: Add `FastScoreWorkflowService::scorePost(array $post): array`; keep it independent of full analysis and agent-reply generation.
- Verification approach: Integration-test root and reply results, disabled/model-failure outcomes, and no invocation of the existing analysis or reply paths.
- Risks or open questions:
  - The first downstream threshold consumer remains out of scope.
- Canonical components/API contracts touched: `PostWorkflowService` post lookup/context conventions; new internal workflow service.

## Stage 6
- Goal: Expose the score to approved operators without adding reader-facing automation or display.
- Dependencies: Stage 5; existing approved-viewer authorization.
- Expected changes: Add approved-only `POST /api/score_post` accepting a post ID and returning status, probability, source, and signals; add no automatic browser work or post-card score display.
- Verification approach: Integration-test authorization, missing posts, root/reply response contracts, and API route registration.
- Risks or open questions:
  - The endpoint supplies evidence, not a reader-facing threshold decision.
- Canonical components/API contracts touched: `PostWorkflowApiController`, API route listing, and approved-viewer authorization.

## Stage 7
- Goal: Document and verify the operable end-to-end contract.
- Dependencies: Stages 1–6.
- Expected changes: Update operator configuration/API references and add focused config, scorer, heuristic, provider-exchange, and API regression coverage; no database schema change is planned because scores are request-scoped and model requests use the existing exchange audit store.
- Verification approach: Run targeted tests, then the project suite; manually inspect one recorded model exchange and one heuristic-only response.
- Risks or open questions:
  - Collect representative outcomes before enabling a downstream threshold decision.
- Canonical components/API contracts touched: `docs/examples/secrets.php.example`, API reference, test runner, and LLM-exchanges tool.
