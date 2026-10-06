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
