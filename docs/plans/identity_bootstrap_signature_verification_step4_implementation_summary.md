> **Feature plan:** [Step 1](./identity_bootstrap_signature_verification_step1_solution_assessment.md) · [Step 2](./identity_bootstrap_signature_verification_step2_feature_description.md) · [Step 3](./identity_bootstrap_signature_verification_step3_development_plan.md) · [Step 4](./identity_bootstrap_signature_verification_step4_implementation_summary.md)

## Stage 1 - Safe verifier outcomes
- Changes:
  - Extended `OpenPgpSignatureVerifier::verifyDetached()` results with allowlisted import/verification exit results, import acceptance, `VALIDSIG` presence, and parsed GnuPG status-code names.
  - Preserved verification acceptance, existing status values, raw output handling, and timing results.
  - Added success and tampered-signature assertions for the new diagnostic fields.
- Verification:
  - `php -l src/ForumRewrite/Security/OpenPgpSignatureVerifier.php`
  - `php -l tests/OpenPgpKeyInspectorTest.php`
  - `php tests/run.php OpenPgpKeyInspectorTest IdentityBootstrapDiagnosticsTest` — 7 run, 7 passed.
- Notes:
  - The diagnostics structure contains only process results and status-code tokens; it does not expose key, signature, canonical-record, or raw GnuPG-output content.

## Stage 2 - Correlated identity API diagnostics
- Changes:
  - Extended the existing prepare/create identity contract with optional, validated bootstrap attempt ID, retry index, and OpenPGP bundle-version context; persisted it with the prepared bootstrap and require the create request to match it.
  - Enriched the existing server failure event with the Stage 1 safe verifier outcomes and the matching correlation context.
  - Added coverage for absent, valid, and invalid diagnostic context plus the expanded safe event payload.
- Verification:
  - `php -l src/ForumRewrite/Write/LocalWriteService.php`
  - `php -l tests/IdentityBootstrapDiagnosticsTest.php`
  - `php tests/run.php IdentityBootstrapDiagnosticsTest OpenPgpKeyInspectorTest` — 8 run, 8 passed.
  - `git diff --check` — passed.
- Notes:
  - Metadata is optional for compatibility with existing clients, but when supplied it is validated and bound to the prepared bootstrap so an unrelated create request cannot relabel the log event.

## Stage 3 - Browser attempt context and reportable failure
- Changes:
  - The canonical browser identity publish/retry path creates one non-secret attempt ID, preserves it across both existing attempts, and sends retry index plus selected loader version to both identity endpoints.
  - Persistent signature-verification failure now retains the existing manual-key guidance and adds `signature_verification_failed` plus the attempt ID.
  - Extended the retry-flow test to assert both requests for both attempts carry one ID, indexes `0`/`1`, and the selected `v6` bundle version.
- Verification:
  - `php -l tests/BrowserSigningNormalizationTest.php`
  - `node --check public/assets/browser_signing.js`
  - `php tests/run.php BrowserSigningNormalizationTest` — 65 run, 63 passed; the two failures (`testThreadSubmitRendersPendingShellBeforeApiResponseAndNavigatesOnSuccess`, `testInlineReplySubmitRendersPendingCardBeforeApiResponseAndNavigatesOnSuccess`) are documented long-standing failures, each failing for 21 runs before this change.
  - `git diff --check` — passed.
- Notes:
  - The immediate retry predicate, attempt count, signing sequence, and manual-key fallback are unchanged; this slice only makes the attempts observable and reportable.
  - Follow-up: retained the raw signature-verification status and attempt ID together in the expandable technical details while keeping the safe diagnostic code in the main message and the retry marker internal.
  - Follow-up: made browser-key and reaction technical-detail controls one-way reveals using a hidden line break; the link disappears and the copied diagnostic text starts on its own line without forcing hidden content visible.

## Stage 4 - Automated coverage and staging procedure
- Changes:
  - Added the staging diagnostic procedure to the existing OpenPGP release runbook: run the read-only smoke probe, then use separate fresh HTTP/v5 and HTTPS/v6 browser profiles and join any failure by its safe attempt ID.
  - Reused the existing verifier, diagnostic, browser-flow, loader-selection, and live-asset probe tests; no new deployment or write command was introduced.
- Verification:
  - `php tests/run.php IdentityBootstrapDiagnosticsTest OpenPgpKeyInspectorTest OpenPgpLoaderTest OpenPgpAssetSmokeProbeTest BrowserSigningNormalizationTest` — 82 run, 80 passed.
  - `git diff --check` — passed.
  - Staging browser smoke: not run. No staging hostname or external write authorization was provided.
- Notes:
  - The remaining two browser-suite failures are long-standing pending-shell failures (`testThreadSubmitRendersPendingShellBeforeApiResponseAndNavigatesOnSuccess` and `testInlineReplySubmitRendersPendingCardBeforeApiResponseAndNavigatesOnSuccess`), each recorded by the runner before this slice and unrelated to identity bootstrap.
  - A fresh-browser staging run on both origins is still required before release; it is an external verification gate, not a reason to change retry behavior.
