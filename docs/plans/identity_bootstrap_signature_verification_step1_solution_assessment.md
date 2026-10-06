> **Feature plan:** [Step 1](./identity_bootstrap_signature_verification_step1_solution_assessment.md) · [Step 2](./identity_bootstrap_signature_verification_step2_feature_description.md) · [Step 3](./identity_bootstrap_signature_verification_step3_development_plan.md) · [Step 4](./identity_bootstrap_signature_verification_step4_implementation_summary.md)

## Original Query

Please review docs/plans/identity_bootstrap_signature_verification_investigation.md and write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md.

## Problem statement

First-use identity bootstrap can transiently fail server-side signature verification despite succeeding on a later post, but the current status cannot identify the failing condition safely enough to choose a behavioral fix.

## Option A — Diagnostic-first vertical slice

- Add safe request/attempt correlation and separate safe verifier outcome fields; show a safe diagnostic code and attempt ID with the existing retry/manual-key guidance.
- Pros: resolves the key uncertainty without exposing cryptographic material; preserves current write behavior; supports a deployed browser-to-server smoke test.
- Cons: does not immediately improve recovery beyond the existing retry.

## Option B — Delayed bounded retry now

- Replace the immediate retry with one short delayed fresh retry, retaining the manual-key fallback.
- Pros: directly targets the observed repeat-post recovery; bounded and pre-persistence.
- Cons: may mask a persistent verifier or deployment fault before it is understood; delay and retry choice would be speculative.

## Option C — Rework identity-bootstrap persistence/reconciliation

- Introduce durable bootstrap states to recover interrupted or repeated identity creation.
- Pros: addresses broader interruption and duplicate-write cases.
- Cons: disproportionate to a failure that occurs before persistence; adds stateful product and operational complexity.

## Recommendation

Choose **Option A**. It is a viable vertical slice: a new-user bootstrap failure yields safe, correlatable evidence and actionable recovery without altering identity creation semantics. Reassess Option B only if the resulting evidence confirms a transient first-attempt failure; reject Option C unless evidence shows persisted partial state or duplicate creation.
