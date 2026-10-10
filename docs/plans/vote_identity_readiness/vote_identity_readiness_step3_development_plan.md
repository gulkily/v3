> **Feature plan:** [Step 1](./vote_identity_readiness_step1_solution_assessment.md) · [Step 2](./vote_identity_readiness_step2_feature_description.md) · [Step 3](./vote_identity_readiness_step3_development_plan.md) · [Step 4](./vote_identity_readiness_step4_implementation_summary.md)

# Vote Identity Readiness — Step 3: Development Plan

## Completion Contract

- Normal entry: a stored usable identity opens a regular, lazy-loaded, or QDB vote page.
- End-to-end outcome: idle work finishes publication, verification, and identity-hint readiness without creating keys; the first vote follows the current write flow without `Preparing identity...`.
- Required recovery: missing identities retain current first-action setup; failed/unfinished background work retries visibly on the vote and writes nothing early.
- Deployment/external verification: confirm fingerprinted assets for each reaction-page mode; manually test stored-unready and no-identity browsers.
- Release condition: focused browser tests and the application suite pass with no duplicate readiness work or reaction record in a readiness/vote race.

## Key Risks

- **High risk: Background publication associates a stored local key with a visit.** Early validation: inspect requests for an unpublished-key fixture. Mitigation: require a usable stored keypair and document the behavior before release.
- **High risk: Readiness and a vote can duplicate side effects.** Early validation: automated overlap test. Mitigation: share canonical identity coordination and reaction de-duplication.
- **High risk: Lazy reaction pages lack a ready signing runtime.** Early validation: asset-mode tests. Mitigation: extend the canonical loader and retain first-click recovery.

## Stage 1 - Complete existing-identity prewarm

- Goal: Fully ready a stored usable identity during idle time without entering creation.
- Dependencies: Approved Step 2; canonical local-identity and readiness flow.
- Expected changes: Add an existing-identity-only readiness operation (for example, `ensureStoredIdentityReady(root, timing)`) to the existing prewarm path; leave `ensureReadyIdentity(root, statusNode, options)` as the intentional-action contract.
- Verification approach: Test stored ready/unready and missing/incomplete identity states; prove the latter make no key-generation, prompt, or publication request.
- Risks or open questions: Impact: background publication is a network side effect. Early warning: inspect unready-key requests. Mitigation: gate before readiness begins.
- Canonical components/API contracts touched: browser identity storage, readiness coordination, publication, verification, and identity-hint contracts.

## Stage 2 - Wire all reaction-page entry points

- Goal: Start that canonical prewarm on regular, lazy-loaded, and QDB reaction pages.
- Dependencies: Stage 1.
- Expected changes: Extend existing signing initialization and lazy-loading paths; no parallel reaction runtime or new vote endpoint.
- Verification approach: Cover regular, lazy, and QDB asset modes plus unavailable-loader fallback; prove no-local-identity pages remain idle.
- Risks or open questions: Impact: assets may fail or affect responsiveness. Early warning: force a loader rejection. Mitigation: reuse the loader promise and first-click fallback.
- Canonical components/API contracts touched: browser-signing initializer, lazy-signing loader, reaction roots, and rendered asset paths.

## Stage 3 - Join readiness at vote time

- Goal: Apply a ready vote immediately, or safely await unfinished readiness without misleading feedback.
- Dependencies: Stages 1–2; existing reaction operation de-duplication.
- Expected changes: Extend the shared reaction identity handoff to distinguish ready, in-flight, and failed-background states; leave existing tag writes and optimistic behavior intact.
- Verification approach: Cover ready first vote, click during prewarm, and failed-prewarm retry; assert no duplicate publication/write and no early optimistic reaction.
- Risks or open questions: Impact: UI can imply a saved vote too early. Early warning: assert feedback/request order. Mitigation: preserve pending and rollback behavior until the write succeeds.
- Canonical components/API contracts touched: reaction identity handoff, operation keys, existing apply-tag endpoints, and browser identity readiness state.

## Stage 4 - Release verification and recovery regression

- Goal: Prove the slice preserves creation, recovery, and non-JavaScript behavior.
- Dependencies: Stages 1–3.
- Expected changes: Add focused regression coverage and release checks; no database, endpoint, or template redesign.
- Verification approach: Run syntax checks, focused browser/reaction tests, application suite, fingerprinted-asset checks, and stored-identity/no-identity manual votes.
- Risks or open questions: Impact: silent background failure can mask recovery regressions. Early warning: force loader/publication/verification failures. Mitigation: confirm the next click uses existing visible retry and writes nothing early.
- Canonical components/API contracts touched: existing test harnesses, asset renderer/release output, and Account Key recovery flow.
