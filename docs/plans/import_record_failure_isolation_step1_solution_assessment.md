> **Feature plan:** [Step 1](./import_record_failure_isolation_step1_solution_assessment.md) · [Step 2](./import_record_failure_isolation_step2_feature_description.md) · [Step 3](./import_record_failure_isolation_step3_development_plan.md) · [Step 4](./import_record_failure_isolation_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/import_record_failure_isolation`, standing enrollment P2; [host ledger](./effort_estimation_trial.md#enrollment-index-after-p2).

## Original Query

Please write Step 1 for making the importer not fail on this error (or other similar situations)

## Understood Intent

Resolve `Import failed: Invalid header line: -----BEGIN PGP SIGNATURE-----` and comparable record-classification or record-validation failures so an otherwise usable instance import can proceed with an accurate partial-result report.

## Problem

The importer recognizes detached signatures but subsequently treats accepted thread-label signatures as label records, allowing a record-level parsing exception to abort the entire planning pass.

## Option A — Fix the signed-label case only

Restrict label-specific processing to actual label records so valid detached signatures remain associated with their records.

- Pros: smallest change; directly resolves the reproduced failure without changing import policy.
- Cons: does not address comparable unchecked parsing elsewhere or improve identification of other rejected files.

## Option B — Classify consistently and isolate record failures (recommended)

Apply record-family processing only to the appropriate record types throughout import planning, and report expected per-record validation failures through the existing rejection/dependency model while continuing with independent valid content.

- Pros: fixes the known bug and the same class of failure across supported families; preserves signatures, trust checks and useful partial imports; reports paths, reasons and blocked dependents.
- Cons: requires careful distinction between recoverable content rejection and failures that invalidate the whole operation; broader regression coverage than A.

## Option C — Skip anything that throws

Catch every import exception and continue processing remaining files.

- Pros: appears tolerant of unfamiliar failures.
- Cons: can hide programming, storage or integrity failures and falsely report success; unsafe as an import contract.

## Recommendation and Releasable Scope

Choose B as one bounded slice spanning planning, normal CLI import, dry-run and retry/resume verification. Preserve valid record/signature bytes and existing authority exclusions, conflict handling and dependency rejection. Reject malformed, unverifiable or unsupported source content with actionable path/reason reporting; never report omitted content as complete. Audit supported record families and both detached-signature suffixes for equivalent classification and unchecked content-parsing paths. Keep unsafe archives, unreadable storage, destination corruption, unexpected internal errors and commit/publication failures fatal with appropriate recovery; do not turn them into skipped records. Existing invalid destination content must produce an actionable failure rather than being silently ignored under a source-rejection policy.

Acceptance should reproduce the valid signed-label incident, cover malformed records, orphan/invalid signatures, unsupported files, conflicts and dependent records alongside independent valid content, and verify actual CLI dry-run/import/repeat and publication-failure resume. Confirm no mutation in preview and no orphaned signatures, weakened verification or duplicate commits. This is CLI/import work: no UI redesign, schema change, new dependency, automatic record repair or promise to tolerate every possible exception. Dependency inventory: zero additions or updates proposed. Existing unrelated working-tree changes remain outside this cycle.

## Study Snapshots and Approval

UTC 2026-10-23T18:19:32Z; scope v1 / R1 / P2; start `c86b89314602a17cf435448bb66053f4d750150f` plus pre-existing uncommitted import/publication work. Stable cycle `v3/import_record_failure_isolation`; related `v3/import_without_static_rendering`, separate parser defect, no replacement outcome. Intake recorded after the prior diagnostic source inspection and isolated fixture reproduction; no pristine prospective intake invented. Codex single exposed rater, including prior-cycle estimates. **Intake projected total 0.45; Step 1 handoff 0.45 reaffirmed**, covering investigation, planning, implementation, CLI/dependency/recovery verification and documentation. Smaller than R1 startup recovery, but family-wide classification and safe partial-result/recovery coverage exceed a single conditional fix. Exact host-local R1 report, diff-review and feedback hashes verified against the ledger. No independent rating, measured labor, implementation or accepted outcome; maintainers' acceptance remains pending. Ledger bookkeeping belongs to this cycle's documentation scope and remains uncommitted.

Await **Approved Step 1** before Step 2; implementation is not authorized by this assessment.

Commit authorization: the user subsequently requested committing the uncommitted changes. This explicitly permits committing this draft and its study/debug records before Step 3; it does not approve Step 1, advancement or implementation.
