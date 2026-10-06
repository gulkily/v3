> **Feature plan:** [Step 1](./identity_bootstrap_signature_verification_step1_solution_assessment.md) · [Step 2](./identity_bootstrap_signature_verification_step2_feature_description.md) · [Step 3](./identity_bootstrap_signature_verification_step3_development_plan.md) · [Step 4](./identity_bootstrap_signature_verification_step4_implementation_summary.md)

## Problem

When a new browser identity fails detached-signature verification, the user gets a generic retry/manual-key outcome while operators cannot distinguish the GnuPG condition or correlate the automatic attempts. The failure should remain visible until its cause is established.

## User stories

- As an operator, I want safe, correlated bootstrap-verification diagnostics so that I can identify a reproduced failure without collecting key material.
- As a user, I want an actionable failure outcome with an attempt ID so that I can report a repeatable problem and use the existing recovery path.
- As a developer, I want deployed browser-to-server coverage so that the selected OpenPGP runtime and verifier are tested together.

## Core requirements

- Preserve the existing identity-bootstrap verification, automatic retry count, and manual-key fallback; do not add a delayed retry in this slice.
- Associate prepare and create requests, including retry index and selected browser bundle version, with a non-secret bootstrap attempt ID.
- Record separable safe verifier outcomes, including import and verification result, `VALIDSIG` presence, and parsed GnuPG status-code names.
- Keep keys, signatures, canonical-record contents, raw GnuPG output, and user IDs out of client messages and application diagnostics.
- Retain friendly recovery guidance while exposing a safe diagnostic code and attempt ID when verification fails.

## Completion boundary

- Normal entry: a user posts without an existing browser identity.
- End-to-end outcome: a signature-verification failure produces correlated, safe browser and server diagnostics.
- Needed recovery: the existing automatic retry and `/account/key/` fallback remain available; the failed identity is not persisted.
- Release condition: automated diagnostics coverage and a fresh-browser staging smoke test cover the HTTP/v5 and HTTPS/v6 paths.

## Risks

- **Diagnostic leakage:** sensitive cryptographic or authored data reaches logs or the browser. **Earliest validation:** unit tests with representative raw verifier output. **Mitigation:** allowlist diagnostic fields and assert forbidden values are absent.
- **Correlation drift:** prepare/create/retry events cannot be joined. **Earliest validation:** browser-flow test of both automatic attempts. **Mitigation:** one bootstrap-scoped ID with an explicit retry index.
- **Environment-specific failure remains unreproduced:** local verification passes while staging does not. **Earliest validation:** fresh-browser staging smoke test. **Mitigation:** include runtime bundle version and safe verifier outcomes in the correlated record.

## Shared component inventory

- **Browser identity bootstrap and compose error surface:** extend the canonical preparation/retry/fallback path; no new user flow.
- **`/api/create_identity` contract:** extend the canonical identity-create request/response path; no parallel diagnostics endpoint.
- **`LocalWriteService` bootstrap diagnostic:** extend the existing server log event; no new logging channel.
- **`OpenPgpSignatureVerifier`:** extend the canonical verification outcome supplied to the write path; do not alter verification acceptance.
- **Existing browser and PHP tests:** extend the established identity-bootstrap and diagnostic coverage; no new test harness.

## User flow

1. A user submits a post without a browser identity.
2. The browser prepares and signs the identity, associating the request with a bootstrap attempt.
3. The server verifies the signature and records safe, correlated outcomes if verification fails.
4. The browser performs its existing retry; each attempt remains distinguishable.
5. A persistent failure shows the existing manual-key guidance plus reportable safe diagnostics.

## Success criteria

- A reproduced failure can be joined across preparation, create, retry, browser bundle, and verifier outcome by one non-secret ID.
- Diagnostics distinguish failed import, failed verification, and absent `VALIDSIG` without containing prohibited material.
- A user-facing failure provides the existing recovery guidance and a safe diagnostic code/attempt ID.
- Current retry and no-partial-identity behavior remain unchanged.
- Fresh-browser staging smoke tests pass on both selected HTTP/v5 and HTTPS/v6 paths.
