> **Feature plan:** [Step 1](./single_request_posting_step1_solution_assessment.md) · [Step 2](./single_request_posting_step2_feature_description.md) · [Step 3](./single_request_posting_step3_development_plan.md) · [Step 4](./single_request_posting_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/single_request_posting; [ledger](../effort_estimation_trial.md#cycle-v3single_request_posting)
Extended estimates: enabled
Cycle estimate: 1.05
Status: Implementation in progress

Entry: 2026-10-30 UTC; Step 3 approved, scope v2/B, R1/P2, baseline b3a5b1621e9df25bac6e023c26aca66537852dfe. Planning commit 2bea587fb on feature/single-request-posting. 1.05 reaffirmed; Codex single exposed rater, pristine intake missing, no measured labor. Original S1–S8 estimates: 0.16/0.12/0.20/0.18/0.10/0.10/0.08/0.15, not additive. Shared acceptance belongs to S8. frm.py remains paused.

## Stage 1 - Validate locally constructed signed records

Original estimate: 0.16
Retrospective stage work: 0.16
Changes: Added strict canonical ordinary/bootstrap shape validation, IDs/time window and exact-byte policy checks; signature/repository validation remains in S2 as planned. Updated canonical spec and test registration.
Verification: php tests/run.php SignedPostBundleValidatorTest — 2/2 passed, including unsupported headers/tags, Unicode symbol refusal and timestamp boundary. No HTTP path exists yet.
Notes: Scope unchanged; original estimates retained. No dependency addition.

*— Codex · 2026-10-30 UTC*
