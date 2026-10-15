> **Feature plan:** [Step 1](./retracted_post_llm_exchange_privacy_step1_solution_assessment.md) · [Step 2](./retracted_post_llm_exchange_privacy_step2_feature_description.md) · [Step 3](./retracted_post_llm_exchange_privacy_step3_development_plan.md) · [Step 4](./retracted_post_llm_exchange_privacy_step4_implementation_summary.md)

Debug mode: disabled

## Problem

LLM exchange logs can disclose a retracted post directly or through another post's recorded context. Approval checks alone allow this content to remain accessible through exchange links and saved URLs.

## User Stories

- As an author, I want retraction to suppress exchanges containing my post so that diagnostic pages do not republish it.
- As an operator, I want operator retractions honored by exchange inspection so that moderation has a consistent outcome.
- As an approved viewer, I want unaffected exchanges to remain available so that I can inspect conversations safely.

## Core Requirements

- Use one shared visibility policy before serving post links, exchange lists, or detail pages. Withhold whole exchanges when any dependency is effectively retracted; approval or operator privilege grants no bypass. Direct requests return an unavailable response without payload data.
- Establish complete dependencies for new recordings across all call types and providers, including primary, parent, thread, comments, related content, and forum-derived context. Incomplete provenance must be marked uncertain rather than declared safe.
- Classify historical exchanges only from reliable recorded provenance. Unknown, malformed, or incomplete dependency sets are withheld; do not infer safety through prose matching or the primary-post association alone. Proven unaffected historical exchanges remain available.
- Consult current validated retractions on every served request; unavailable retraction state withholds exchanges. Cover completed/failed calls, late provider results, retries, and cached/generated pages. Filter hidden entries before result limits, without returning hidden payload fields.
- Preserve diagnostic storage and signed retraction evidence. Do not delete or rewrite logs to achieve suppression; explain unavailable exchange access without reproducing the affected content.

## Delivery Scope

Application change covering all existing exchange UI entry points and recording paths. Exclude physical purge, provider-side deletion, previously downloaded/browser-held copies, automatic removal of published replies, and redesign of other analysis tools. New dependencies may use existing recording context; avoid an unrelated schema migration.

## Completion Boundary

Entry is author/operator retraction followed by exchange inspection. Outcome is suppressed links/list entries and denied direct payload access, including exchanges associated with another post. Recovery is repeatable classification after interruption and retry after temporary state failure; uncertainty never opens access. Release requires historical coverage, unaffected-record usability, and consistent behavior after rebuild/restart.

## Risks

- **Incomplete provenance:** Cross-post content leaks; inventory every producer and derived input before Stage 3; require complete dependencies or withhold the exchange.
- **Historical overblocking:** Useful diagnostics disappear; validate real legacy formats early; preserve proven-safe records and document conservative exclusions.
- **Stale decisions and limits:** Late results or cached pages disclose content, or safe results disappear from lists; exercise retraction timing and mixed result sets early; share live validation and filter before limits.

## Shared Component Inventory

- Extend the exchange store/controller for list and direct detail access; reuse existing exchange templates with unavailable handling.
- Extend Application's post exchange lookup and shared root/reply cards used by thread and individual post pages; suppress links through the same policy.
- Extend the recorder and provider context shared by OpenAI-compatible and Anthropic calls, plus analysis, agent-response, agent-reply, and Fastmod producers; add provenance at the point inputs are assembled.
- Reuse validated canonical retraction state and existing artifact retirement; cover any exchange caches with the shared policy. Introduce a shared dependency classifier/visibility component because all readers need the same decision.

## Simple User Flow

1. Author/operator retracts a supported post.
2. Exchange links and affected list entries disappear.
3. A saved exchange URL reports unavailable; proven unaffected exchanges remain inspectable.

## Success Criteria

Retracted prose is absent from served exchange requests, responses, errors, and context. Verify primary/context-only dependencies, historical uncertainty, both roles, saved URLs, provider failures and late completion, result limits, and rebuild/restart. Suppression leaves diagnostic storage and retraction audit evidence intact.

## Review

**Approved Step 2** received; requirements and scope are the basis for Step 3.
