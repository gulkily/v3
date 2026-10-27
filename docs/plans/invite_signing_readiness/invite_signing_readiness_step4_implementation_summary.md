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

## Stage 2 - Authenticate the saved issuer before preparation

**Original estimate: 0.15**
**Retrospective stage-work assessment: 0.15**

- Changes: Invite explicitly loads authentication support and owns authentication at action time, avoiding background redirects. Existing-key-only readiness is forwarded through the shared helper; Invite awaits forced, non-navigating authentication and requires approved status before preparing. Missing keys and unapproved identities stop with recovery guidance; no replacement key generation.
- Verification: Real regression now passes all four public/members-only × stale/current cached fingerprint combinations without Account entry. Held authentication responses prove preparation waits; one prepare/finalize request and unchanged key bytes per action. Node invitation lifecycle checks pass missing-key/unapproved/missing-helper/pending-authentication recovery. PHP InvitationIssuanceTest 9/9, BrowserSigningNormalizationTest 82/82, PrivateSiteAuthTest/AuthNavigationTest 13/13 passed.
- Notes: Stage 1 ad1ff0608. Scoped authentication helper changes preserve default consumers; identity binding during async preparation/signing follows in Stage 3. No dependency/schema changes.

*— Codex · 2026-10-27 UTC*
