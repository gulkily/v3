> **Feature plan:** [Step 1](./import_archive_hardening_step1_solution_assessment.md) · [Step 2](./import_archive_hardening_step2_feature_description.md) · [Step 3](./import_archive_hardening_step3_development_plan.md) · [Step 4](./import_archive_hardening_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/import_archive_hardening; standing P2 enrollment; [host ledger](../effort_estimation_trial.md#cycle-v3import_archive_hardening).

Extended estimates: enabled

**Cycle estimate: 0.90**

## Original Query

“Do you think it's worth trying to address all these in our existing code or to build an initial bounds checker?”

“Please write Step 1.”

## Understood Intent

Harden `./v3 import-instance` against the [review's four reproduced failures and GPG budget concern](../import_archive_adversarial_review.txt), combining early rejection with resilient processing while preserving valid imports and recovery.

## Problem

An archive can satisfy container limits yet exhaust parser memory, bypass companion limits, monopolize planning, break reporting, or enter unbounded signature verification.

## Option A — Shared limits plus existing-code hardening (recommended)

**Estimate: 0.90**

Extend existing archive validation with shared admission limits, enforce them at direct and indirect reads, and repair resource-sensitive parsing, dependency processing and subprocess execution.

- Pros: covers F1–F4 and C1 without a competing parser; consistent limits protect records, signatures, keys and metadata, including destination fallback reads.
- Cons: shared-reader compatibility and timeout/recovery semantics require careful verification; these drive the estimate and its uncertainty.

## Option B — Initial bounds checker only

**Estimate: 0.40**

Add a preliminary gate for filename encoding, file counts, aggregate bytes and format-specific sizes, leaving subsequent processing unchanged.

- Pros: smaller release; rejects obvious oversized or unreportable inputs early.
- Cons: admitted input can still trigger memory amplification, quadratic processing or unbounded GPG work; safe thresholds versus legitimate archives are uncertain. This is partial mitigation, not completion of the requested hardening.

## Option C — Separate fixes in existing components

**Estimate: 0.80**

Address all five findings through component-specific limits and processing fixes, without introducing a shared admission policy.

- Pros: smaller architectural change; reuses existing component boundaries.
- Cons: duplicated limits can drift or miss indirect reads; proving equivalent coverage across callers drives effort and uncertainty.

## Recommendation and releasable scope

Choose A as one bounded release through the normal CLI: early filename/size admission, bounded-memory text validation, efficient rejection propagation, bounded GPG execution/output, and an overall processing-budget and recovery contract. Preserve signature bytes, trust decisions, accepted bootstrap cycles, conflict handling and truthful partial results. Archive safety violations remain fatal; ordinary record rejection must not hide programming/storage failures. Budget exhaustion must release locks, terminate children and leave either an unchanged destination or an accurate resumable state, according to the write phase.

Acceptance covers the reproduced fixtures, controlled slow/noisy children, boundary-size valid inputs, and CLI preview/import/repeat/resume with publication interruption. Verify shared-reader consumers and retain existing traversal/link/integrity defenses. Exact limits and supported-platform enforcement belong in Step 2. No importer rewrite, new trust model, OS sandbox, schema/UI change, or universal security guarantee. Dependency inventory: zero additions or updates proposed. Documentation includes the review, cycle artifacts and study ledger.

## Study checkpoints and approval

Intake: 2026-10-27T00:12:50Z, **0.90**; Step 1 handoff: **0.90 reaffirmed**, same date. Scope v1 / R1 / P2; baseline `00c84badd8832ed9e81fa0980c670283ae9fa318`. Codex single exposed rater after the preceding review/reproductions; no pristine pre-review intake claimed. Frozen R1 hashes verified. Options A/B/C are independent whole-cycle projections from this baseline, including planning, verification, recovery and documentation; not additive or measured time. Existing import/test infrastructure supports one cycle; shared subprocess compatibility is the main sizing uncertainty. Related predecessor: `v3/import_record_failure_isolation`; its broader deferred work and scores remain separate. No implementation or accepted outcome; plans remain uncommitted. Await **Approved Step 1** before Step 2.

*— Codex · 2026-10-27 UTC*
