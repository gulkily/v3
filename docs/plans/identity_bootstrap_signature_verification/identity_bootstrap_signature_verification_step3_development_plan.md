> **Feature plan:** [Step 1](./identity_bootstrap_signature_verification_step1_solution_assessment.md) · [Step 2](./identity_bootstrap_signature_verification_step2_feature_description.md) · [Step 3](./identity_bootstrap_signature_verification_step3_development_plan.md) · [Step 4](./identity_bootstrap_signature_verification_step4_implementation_summary.md)

## Completion Contract

- Normal entry: a user without a browser identity submits a post through the existing compose flow.
- End-to-end outcome: each bootstrap verification failure has a non-secret attempt ID, retry index, selected bundle version, and safe server verifier outcome.
- Required recovery: preserve the existing immediate retry and `/account/key/` fallback; do not persist an identity on verification failure.
- Deployment/external verification: run a fresh-browser staging smoke test against the selected HTTP/v5 and HTTPS/v6 paths.
- Release condition: focused PHP/browser tests and both staging paths pass, with a reproduced failure safely diagnosable when available.

## Key Risks

- **High risk: diagnostic data leaks cryptographic or authored content.**
  - Impact: sensitive data reaches logs or the browser.
  - Early warning/validation: fixture tests containing forbidden raw values.
  - Mitigation: allowlist fields and assert prohibited material is absent before request wiring.
- Attempt events cannot be correlated across the two automatic attempts.
  - Impact: production evidence remains inconclusive.
  - Early warning/validation: browser-flow assertions for prepare/create/retry metadata.
  - Mitigation: generate one bootstrap-scoped ID and send an explicit index on both requests.
- Staging may not reproduce production runtime conditions.
  - Impact: root cause remains unresolved.
  - Early warning/validation: fresh-browser smoke on both deployment paths.
  - Mitigation: retain the safe correlation evidence and compare deployment/runtime context before considering a retry change.

## Stage 1
- Goal: Make the verifier distinguish safe import and verification outcomes.
- Dependencies: none.
- Expected changes: Extend `OpenPgpSignatureVerifier::verifyDetached(...): array` with import exit result, verification exit result, `VALIDSIG` presence, and parsed status-code names; preserve its acceptance result and raw-output containment.
- Verification approach: Expand verifier/diagnostic fixtures for successful verification, failed import, failed verification, and missing `VALIDSIG`; assert no raw verifier material is returned in safe diagnostics.
- Risks or open questions:
  - Impact: an incomplete outcome model would still collapse the observed failure.
  - Early warning / validation: each known branch has an asserted safe result.
  - Mitigation: expose only the required allowlisted fields.
- Canonical components/API contracts touched: `OpenPgpSignatureVerifier::verifyDetached()`, existing `LocalWriteService` diagnostic test.

## Stage 2
- Goal: Carry one safe bootstrap correlation context through the existing identity API and server log.
- Dependencies: Stage 1.
- Expected changes: Extend `prepareIdentityBootstrap(array $input): array` and `createIdentityBootstrap(array $input): array` request/result contracts with a validated attempt ID, retry index, and bundle version; enrich the existing `identity_bootstrap_signature_verification_failed` event with the Stage 1 outcome.
- Verification approach: Exercise the prepare/create API contract with valid and invalid diagnostic context; assert the logged event joins fields without changing creation success/failure semantics.
- Risks or open questions:
  - Impact: malformed client metadata could obscure logs or change a write outcome.
  - Early warning / validation: request-validation and failure-fixture tests.
  - Mitigation: constrain metadata to a small non-secret contract and treat absent/invalid optional diagnostics predictably.
- Canonical components/API contracts touched: `/api/prepare_identity`, `/api/create_identity`, `WritePostAndIdentityApiController`, `LocalWriteService`.

## Stage 3
- Goal: Attach the context to each existing browser attempt and make a persistent failure reportable.
- Dependencies: Stage 2.
- Expected changes: Extend the canonical browser preparation/signing/retry path to create one bootstrap ID, increment retry index, read the selected OpenPGP bundle version, and render returned safe diagnostic code/attempt ID with unchanged recovery guidance.
- Verification approach: Simulate one failure then success and two failures; assert request metadata, unchanged attempt count, no duplicate identity, and final fallback text plus safe details.
- Risks or open questions:
  - Impact: retry metadata could accidentally alter retry or publishing behavior.
  - Early warning / validation: existing browser normalization flow assertions.
  - Mitigation: keep retry predicate/count and write sequence unchanged.
- Canonical components/API contracts touched: `browser_signing.js`, existing OpenPGP loader contract, `/api/prepare_identity`, `/api/create_identity`.

## Stage 4
- Goal: Prove the diagnostic vertical slice against automated and deployed browser paths.
- Dependencies: Stages 1–3.
- Expected changes: Add focused PHP/browser regression coverage and a documented fresh-browser staging smoke procedure for HTTP/v5 and HTTPS/v6; no schema or behavior change.
- Verification approach: Run the focused suites, then complete both staging paths and retain only the safe attempt IDs/outcomes from any failure.
- Risks or open questions:
  - Impact: local mocks can pass while deployed assets or GnuPG differ.
  - Early warning / validation: fresh profiles on both staging origins.
  - Mitigation: staging smoke is required for release, not replaced by unit tests.
- Canonical components/API contracts touched: existing PHP test runner, browser-signing tests, deployment/staging verification workflow.
