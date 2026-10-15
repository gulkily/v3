> **Feature plan:** [Step 1](./retracted_post_llm_exchange_privacy_step1_solution_assessment.md) · [Step 2](./retracted_post_llm_exchange_privacy_step2_feature_description.md) · [Step 3](./retracted_post_llm_exchange_privacy_step3_development_plan.md) · [Step 4](./retracted_post_llm_exchange_privacy_step4_implementation_summary.md)

Debug mode: disabled

## Completion Contract

- Entry/outcome: retraction hides affected links/list entries and denies saved URLs; proven unaffected exchanges remain inspectable.
- Recovery: repeatable classification; unavailable state/uncertain dependencies deny access. Preserve diagnostic rows.
- Deployment: validate legacy logs, restart/rebuild and caches locally; preserve audit evidence. No external deployment/provider deletion.
- Release: primary/context-only suppression, legacy uncertainty, both roles, late/errors and limits verified. Seven stages, ≤1 hour each; rescope beyond one day/eight stages.

## Key Risks

- **High risk: provenance.** Copied/derived text leaks; inventory producers/summaries in Stage 1. Require complete dependencies or deny; unresolved proof blocks dependents.
- **High risk: overblocking.** Diagnostics disappear; test legacy formats early, preserve proven-safe records; never infer safety from prose matching.
- **High risk: stale state.** Late results/caches leak; test call timing/state failure early. Validate when serving; prohibit rollback to unguarded readers.

## Stage 1

- Goal: prove the dependency and retraction-state contract.
- Dependencies: approved Step 2.
- Expected changes: classify complete/uncertain provenance; legacy allowlist by proven format. Contract: classify(array exchange): ?array of dependency IDs.
- Verification approach: primary/context/summary, malformed/unknown, missing-post/history fixtures.
- Risks or open questions: false safety; prove allowlisted formats and incomplete-state denial before Stage 2.
- Canonical components/API contracts touched: exchange context, canonical retraction validation, new shared dependency classifier; inventory all producers.

## Stage 2

- Goal: block affected saved exchange URLs.
- Dependencies: Stage 1 proof.
- Expected changes: allows(array exchange): bool policy; detail lookup checks current validated retractions before rendering.
- Verification approach: both roles, context-only, guessed ID, legacy uncertainty, unaffected detail.
- Risks or open questions: approval bypass or state failure; deny before rendering payloads, for every role.
- Canonical components/API contracts touched: Application store factory, SqliteLlmExchangeStore, LlmExchangesController, shared visibility policy.

## Stage 3

- Goal: remove affected links and list entries consistently.
- Dependencies: Stage 2.
- Expected changes: guard recent/find/forPost; filter before visible limits/offsets; reuse templates.
- Verification approach: mixed/over-limit hidden rows; thread/root/reply/post links; unknown records.
- Risks or open questions: empty lists or expensive scans; bounded iteration with one validated state snapshot per served request.
- Canonical components/API contracts touched: exchange store queries, Application post lookup, exchange list/detail and shared post/root cards.

## Stage 4

- Goal: establish provenance for analysis and Fastmod recordings.
- Dependencies: Stage 1 contract and Stage 3 readers.
- Expected changes: retain complete dependencies in existing context; propagate summary provenance or mark uncertain. No core schema migration.
- Verification approach: target/parent/root, comments, cross-thread matches, cached summaries with known/unknown origins, scoring calls.
- Risks or open questions: derived text without provenance; mark uncertain rather than assuming its owning post is sufficient.
- Canonical components/API contracts touched: PostWorkflowService, DedalusPostAnalyzer, FastPostScorer, LlmExchangeRecorder.

## Stage 5

- Goal: establish provenance for agent-response/reply recordings.
- Dependencies: Stage 4 conventions.
- Expected changes: carry captured-input dependencies through tasks, queued execution, retries and recording.
- Verification approach: parent preview/root subject, queued snapshots, both providers, incomplete legacy tasks, retry.
- Risks or open questions: stale tasks; classify captured input rather than current replacements.
- Canonical components/API contracts touched: AgentResponseTask/Generator, agent-reply preparation/fulfillment, shared recorder/context.

## Stage 6

- Goal: close failure, late-result, and cache bypasses.
- Dependencies: Stage 5.
- Expected changes: retain error provenance and serve-time checks; prevent stale HTML delivery.
- Verification approach: in-flight retraction, error prose, missing state, cached URL, restart/rebuild.
- Risks or open questions: stale permission decisions; no cross-request visibility cache without retraction-state invalidation.
- Canonical components/API contracts touched: OpenAI-compatible/Anthropic providers, recorder, exchange controller, FrontController/artifact cache paths.

## Stage 7

- Goal: verify and document the complete privacy boundary.
- Dependencies: Stage 6.
- Expected changes: integrated regression coverage, historical-handling and storage/rollback reference updates.
- Verification approach: marker absent on all exchange surfaces; storage/audit intact; unaffected access. Focused/full tests and browser flow.
- Risks or open questions: retention claims/baseline failures; document limits/regressions; do not recover diagnostics by bypassing suppression.
- Canonical components/API contracts touched: exchange/store/provider/retraction tests, browser checks, retraction and LLM exchange references.
