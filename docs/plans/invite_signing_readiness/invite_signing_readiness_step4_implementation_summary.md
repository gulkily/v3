> **Feature plan:** [Step 1](./invite_signing_readiness_step1_solution_assessment.md) · [Step 2](./invite_signing_readiness_step2_feature_description.md) · [Step 3](./invite_signing_readiness_step3_development_plan.md) · [Step 4](./invite_signing_readiness_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/invite_signing_readiness; standing P2 enrollment; [ledger](../effort_estimation_trial.md)
Extended estimates: enabled
Status: Implementing on feature/invite-signing-readiness; Step 3 approved

**Cycle estimate: 0.50**

## Entry checkpoint

2026-10-27 UTC: Step 3 projection 0.50 reaffirmed before implementation. Scope v1/A, baseline 89a9abbe044a39240a30ab7787977ce735050762, R1/P2, same exposed Codex rater; pristine intake missing as previously recorded. Planning commit 554114c2c. Inherit original stage-work S1 reproduction 0.10 (session timing), S2 reconciliation 0.15 (shared authentication), S3 binding/recovery 0.15 (async outcomes), S4 acceptance/closeout 0.15 (consumer breadth). Stage values are not additive or measured labor; S4 owns shared acceptance. QR visual review remains separate and uncommitted.

*— Codex · 2026-10-27 UTC*

## Stage 1 - Durable direct-entry regression

**Original estimate: 0.10**
**Retrospective stage-work assessment: 0.10**

- Changes: Existing isolated fixture can approve a second test identity. Added invite_signing_readiness.mjs with normal first-attempt expectations and an explicit --baseline mode that verifies two exact signature failures followed by Account recovery. Both use real keypairs, authenticated sessions and server verification; no Account preconditioning.
- Verification: --baseline passes public and members-only cases on unchanged application code. Normal regression fails with error versus expected ok, exactly as intended before repair. Public/private key bytes remain unchanged through Account recovery. Earlier separate diagnostic additionally proved the same-page authentication control and public mode with correct cached fingerprint; retained in reproduction.txt.
- Notes: No production changes in this stage. Stage 2 will turn the normal regression green; --baseline is for historical revisions, not an expected post-fix pass. No measured labor or incident-specific device confirmation.

*— Codex · 2026-10-27 UTC*
