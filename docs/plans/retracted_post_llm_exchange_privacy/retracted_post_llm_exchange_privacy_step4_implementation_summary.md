> **Feature plan:** [Step 1](./retracted_post_llm_exchange_privacy_step1_solution_assessment.md) · [Step 2](./retracted_post_llm_exchange_privacy_step2_feature_description.md) · [Step 3](./retracted_post_llm_exchange_privacy_step3_development_plan.md) · [Step 4](./retracted_post_llm_exchange_privacy_step4_implementation_summary.md)

Debug mode: disabled

## Stage 1 - Provenance and strict retraction state

- Changes: shared versioned dependency manifest/classifier validates complete post ID sets and primary-post membership. Historical allowlist covers only root Fastmod requests with the standard system prompt and exact known input shape, on both provider layouts. Other legacy formats remain uncertain. Strict canonical-state reading distinguishes empty state from unavailable, malformed, forged, and missing-target evidence.
- Verification: three focused tests passed for complete/incomplete manifests, both legacy provider layouts, unidentified context, invalid IDs, missing target, and forged retraction signature.
- Notes: unknown derived-summary provenance is never inferred from its owning post. Missing operator history makes exchange access unavailable rather than assuming no retraction. Diagnostic payloads are not modified.

