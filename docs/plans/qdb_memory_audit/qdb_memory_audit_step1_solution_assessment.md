> **Feature plan:** [Step 1](./qdb_memory_audit_step1_solution_assessment.md) · [Step 2](./qdb_memory_audit_step2_feature_description.md) · [Step 3](./qdb_memory_audit_step3_development_plan.md) · [Step 4](./qdb_memory_audit_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/qdb_memory_audit; [standing P2 ledger](../effort_estimation_trial.md#cycle-v3qdb_memory_audit)
Extended estimates: enabled

## Original Query

Can you also please audit for anywhere else this may be an issue?

## Understood Intent

Find other causes of PHP memory exhaustion after two QDB build-static failures, with a fixed 128 MiB host limit. The requested deliverable is an audit, not approval for a broad implementation or production changes.

Problem: Several operations limit displayed/output records only after allocating corpus-sized input collections.

Concepts touched: Derived read-model rebuild, static publication, public quote listings and activity evidence, used unchanged; inventory entries proposed if remediation proceeds. Existing [reaction records](../../concepts/reaction_record.md) used unchanged.

## Option A — Source-only audit (selected)

**Estimate: 0.20**

Inspect allocation patterns and rank likely risks without workload reproductions.

- Smallest scope; uncertainty remains about actual peaks and call-path reachability.

## Option B — Focused audit with executable probes

**Estimate: 0.40**

Trace the build and shared readers, then reproduce selected risks using disposable data at the existing memory limit.

- Previously recommended; probes already performed remain supporting evidence, not a requirement for further work in the selected A scope.
- Synthetic corpus construction and interpretation drive effort; live workload remains unknown.

## Option C — Application-wide capacity study

**Estimate: 0.90**

Measure every major command/request family, concurrency and native/child-process memory on production-representative corpora.

- Broadest evidence; requires additional representative data and environment coverage. This audit does not claim that outcome.

## Findings and selected scope

[Audit findings and reproductions](./qdb_memory_audit_findings.txt) record ten findings. Rebuild and QDB rendering fail with 40000 synthetic quotes without padding; offline selection alone peaks at 94.01 MiB; large commit manifests, high-traffic reads and malformed headers expose further risks. The [archived probe source](./qdb_memory_audit_probe_source.txt) preserves repeatable cases.

Selection, 2026-10-30: The user chose Option A and requested a separate plan for expanding to Option C. This task owns the source inventory, ranked allocation risks and remediation boundaries. The diagnostic inventory is complete. Existing probes remain evidence; no further workload campaign is included here. The broader measurement/acceptance work is proposed in [Application capacity study](../application_capacity_study_step1_solution_assessment.md). No fixes or next-step approval are implied by this selection.

Budget proposal carried to the expansion: Ordinary peaks at or below 16/48 MiB and test ceilings of 32/64 MiB for public/maintenance work, plus scaling, edge-case and recovery measurements. These remain proposals, not verified production limits. The original 128 MiB failures and tiny fixture's 6 MiB peak remain unchanged evidence. Never meet budgets by dropping records or changing content limits.

Overlap: M02/M04/M09 seed the separate [load-before-selection assessment](../load_before_selection_audit_step1_solution_assessment.md), which owns codebase-wide unnecessary-work discovery. This task retains the original memory findings; the capacity-study expansion owns proposed broader measurements. Link shared causes and fixes once. The selection audit's own option remains unchanged.

## Study checkpoint

Scope: v1/B, baseline cfb8364f6db03cc09e27cd26afb93c109c723a08; R1/P2. [Intake record](./qdb_memory_audit_intake.txt) preserves prior inspection exposure and missing pristine estimate. Step 1 handoff: 2026-10-30 UTC, 0.40 total reaffirmed; all three frozen hashes verified. Codex single exposed rater; options are whole-audit projections, not additive remediation estimates. No independent rating, measured labor, implementation outcome or maintainer acceptance claimed. Related direct fixes remain outside the study.

Selection checkpoint, 2026-10-30: v2/A supersedes the earlier B recommendation prospectively. Preserve A's original 0.20 alternative estimate and v1/B's 0.40 snapshot. Current whole-cycle projection remains 0.40 because already-performed probes and documentation remain part of this cycle; do not retroactively recast it as a source-only execution. R1/P2, baseline and exposed rater unchanged. Expansion is separately proposed as v3/application_capacity_study; neither outcome is counted accepted.

Approval, 2026-10-30: User explicitly clarified that Approved Step 1 applies to this QDB memory audit, selected Option A. Proceed to Step 2 for v2/A. The application capacity study and load-before-selection assessment remain unapproved at Step 1; no application fixes or measurement expansion are authorized.

*— Codex · 2026-10-30 UTC*
