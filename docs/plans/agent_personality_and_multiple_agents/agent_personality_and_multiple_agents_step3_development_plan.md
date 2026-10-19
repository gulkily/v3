> **Feature plan:** [Step 1](./agent_personality_and_multiple_agents_step1_solution_assessment.md) · [Step 2](./agent_personality_and_multiple_agents_step2_feature_description.md) · [Step 3](./agent_personality_and_multiple_agents_step3_development_plan.md) · [Step 4](./agent_personality_and_multiple_agents_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

Step 2 approved; implement Option A only. Automatic processing, Request agent response, and Expand must deliver personable replies with authoritative participant context. Preserve access, scripture verification, privacy, and duplicate prevention. Release requires Stage 5 evidence, including failure → diagnosis/correction → targeted retry. Deploy code/prompts together and restart workers; rollback restores prior code/prompts without rewriting replies. Production deployment is excluded. Multiple agents remains separate.

## Key Risks

- **High risk:** status confusion can misrepresent authority. Use profile approval and `ApprovalPolicy::isOperator` for admin (root-approved operator); missing identity/evidence is unknown. Validate independent states and forged forum/client claims first; never change authorization.
- **High risk:** legacy drafts, expansion provenance, and rollback can duplicate replies or expose context. Distinguish fresh generation from publication recovery; preserve stored completed drafts, source checks, and privacy manifests.
- Tone is subjective; freeze examples before edits and apply Step 2’s rubric to actual model replies, not just fixtures.

## Stage 1

- Goal: deliver authoritative status through ordinary requested replies.
- Dependencies: approved plan and planning commit.
- Expected changes: shared generation-time participant resolver; author from canonical post identity, requester from stored request identity; explicit unknowns and trusted prompt section. Proposed contract: `resolve(array $post, ?string $requesterIdentityId): array`; no schema changes intended.
- Verification approach: requested reply end-to-end; independent approval/operator states, missing profiles, differing requester, queued status changes, spoofed claims.
- Risks or open questions: misattribution; trace provenance first and send only role/status facts.
- Canonical components/API contracts touched: ProfileRepository, ApprovalPolicy, PostWorkflowService, AgentReplyFulfillmentService, AgentResponseTask/Generator; public request shape unchanged.

## Stage 2

- Goal: deliver the approved voice across modes.
- Dependencies: Stage 1.
- Expected changes: shared personality guidance in ordinary/verified prompts; preserve mode and JSON/text contracts; freeze review examples first.
- Verification approach: generator/task tests for all modes and scripture configurations; review examples against Step 2 rubric.
- Risks or open questions: personality overriding task/quotation constraints; keep those constraints authoritative and test both output formats.
- Canonical components/API contracts touched: agent response prompts, AgentResponseGenerator; existing mode catalog reused.

## Stage 3

- Goal: cover automatic processing, legacy requests, and retry consistently.
- Dependencies: Stage 2.
- Expected changes: fresh legacy replies use the common writer after analysis gates; fresh retries refresh status; completed drafts/publications retain recovery semantics.
- Verification approach: automatic/legacy/queued paths, failure then targeted retry, completed-draft recovery, repeat execution without duplicate publication.
- Risks or open questions: extra legacy writer call adds cost/failures; test absent providers and retain analysis gates.
- Canonical components/API contracts touched: fulfillment, generation store, task queue and CLI; existing storage contracts retained.

## Stage 4

- Goal: preserve personality and correct participants through Expand.
- Dependencies: Stage 3.
- Expected changes: resolve original human author and expansion requester separately; preserve source dependencies.
- Verification approach: expansion lifecycle, different requester, approval changes, unknown author, quotation failure, retraction, and retry.
- Risks or open questions: attributing the initial agent's identity to the human; validate source provenance before generation.
- Canonical components/API contracts touched: ScriptureExpansionService, generator, expansion/status APIs and shared display partial.

## Stage 5

- Goal: establish release evidence and document operation.
- Dependencies: Stage 4.
- Expected changes: focused integration coverage, recorded rubric results, CLI/reference recovery guidance and implementation summary.
- Verification approach: `./v3 agent-reply test-local`, affected scripture/privacy/API tests; standard/Forte thread/permalink/Expand checks; bounded isolated live ordinary/Bible/Jesus/scripture samples; rollback/restart and targeted retry.
- Risks or open questions: fake-provider tests conceal quality defects; retain actual outputs and disclose unavailable live checks as incomplete acceptance.
- Canonical components/API contracts touched: existing UI/API/CLI surfaces, tests and reference documentation; no new navigation or UI copy planned.

Target ≤1 hour per stage; split oversized stages before work. Commit each stage with its summary. Any schema change requires plan revision and CONTRIBUTING’s version/spec/rebuild checks.
