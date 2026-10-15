> **Feature plan:** [Step 1](./retracted_post_llm_exchange_privacy_step1_solution_assessment.md) · [Step 2](./retracted_post_llm_exchange_privacy_step2_feature_description.md) · [Step 3](./retracted_post_llm_exchange_privacy_step3_development_plan.md) · [Step 4](./retracted_post_llm_exchange_privacy_step4_implementation_summary.md)

Debug mode: disabled

## Stage 1 - Provenance and strict retraction state

- Changes: shared versioned dependency manifest/classifier validates complete post ID sets and primary-post membership. Historical allowlist covers only root Fastmod requests with the standard system prompt and exact known input shape, on both provider layouts. Other legacy formats remain uncertain. Strict canonical-state reading distinguishes empty state from unavailable, malformed, forged, and missing-target evidence.
- Verification: three focused tests passed for complete/incomplete manifests, both legacy provider layouts, unidentified context, invalid IDs, missing target, and forged retraction signature.
- Notes: unknown derived-summary provenance is never inferred from its owning post. Missing operator history makes exchange access unavailable rather than assuming no retraction. Diagnostic payloads are not modified.

## Stage 2 - Guard direct exchange access

- Changes: shared visibility policy takes a fresh strict retraction snapshot and denies affected or uncertain dependency sets. Application's UI store now guards find(); unavailable detail responses disclose no payload, irrespective of viewer approval. Raw internal store access remains available for diagnostics, without serving it through the application.
- Verification: five focused tests passed. Integration coverage verifies direct and context-only denial after signed author retraction, absence of request/response/error prose, unrelated detail availability, and unchanged diagnostic rows.
- Notes: list and post-link reads are wired in Stage 3; no operator inspection bypass is introduced.

## Stage 3 - Filter lists and post links before limits

- Changes: UI recent()/forPost() queries share the detail visibility policy, using one fresh state snapshot per query. Rows stream with bounded PHP memory; hidden rows consume neither visible offsets nor limits. Existing Application lookup and post/root cards inherit filtering without duplicate template policies.
- Verification: six targeted tests passed, including 205 newer hidden rows above older visible results, visible offsets, post-link suppression, list filtering, direct denial, and unchanged raw row count.
- Notes: raw internal store reads retain their existing behavior; every application exchange UI reader uses the guarded store.

## Stage 4 - Analysis and Fastmod input provenance

- Changes: analysis input assembly records primary/root/parent/comment/related dependencies and propagates cached-summary origins. Analysis service stores provenance alongside new cached analysis diagnostics for every analyzer; unknown legacy summary origins mark the next exchange uncertain. Fastmod input assembly captures parent/root dependencies. Producers pass provenance to recording context, excluding it from model prompts.
- Verification: 29 producer/integration tests plus four provenance tests passed; coverage verifies cross-thread summary propagation, unknown cached-summary denial, bounded scoring context, prompt exclusion, and persisted thread context. Initial 41 producer/store tests also passed.
- Notes: no core schema change or diagnostic-log rewrite. Legacy analysis summaries without evidence remain usable by existing analysis behavior but cannot make an exchange inspectable.

## Stage 5 - Agent prompt provenance

- Changes: agent task input captures the primary post and the actual thread-subject/parent-preview dependencies. Generator passes the captured manifest to exchange recording without adding it to model prompts; missing identity evidence marks legacy inputs uncertain. Unused analysis summaries/comments do not overblock a task that never sends them.
- Verification: 27 agent/task/store/integration tests passed, including captured-input stability after source changes, unknown parent provenance, bounded prompts, and persisted request context.
- Notes: queued fulfillment retains existing content/hash validation and input assembly behavior. Legacy replies sourced from completed analysis make no additional provider call; their recorded analysis exchanges are covered by Stage 4.

## Stage 6 - Failure, timing, and cache boundaries

- Changes: exchange UI responses explicitly use private, no-store caching. Shared readers retain live state checks for completed/error rows recorded after retraction. Existing provider recording already preserves the entire supplied context on failures; verified both transports rather than duplicating their recording logic.
- Verification: 15 focused provider/store/API tests passed, including real transport failure on loopback for both providers, retained provenance and redacted credentials, late/error records after retraction, existing store state changes, and malformed-state denial.
- Notes: static builder/front-controller mappings do not generate or serve exchange pages; service-worker exchange navigation is network-first with no exchange fallback. Browser headers and stale-artifact behavior receive end-to-end coverage in Stage 7. No cross-request visibility cache is introduced.

