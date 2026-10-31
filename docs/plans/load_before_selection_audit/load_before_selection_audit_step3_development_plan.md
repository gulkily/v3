> **Feature plan:** [Step 1](load_before_selection_audit_step1_solution_assessment.md) · [Step 2](load_before_selection_audit_step2_feature_description.md) · Step 3 · Step 4 (not started)

Debug mode: enabled
Effort study: railspike-effort / v3/load_before_selection_audit; [P2 ledger](../effort_estimation_trial.md#cycle-v3load_before_selection_audit)
Extended estimates: enabled

**Cycle estimate: 0.55**

## Completion Contract

Documentation-only: give maintainers ranked root causes, consumer coverage, exceptions and reproducible evidence for choosing fixes. Concepts touched and allowed writes: this cycle's planning/evidence folder, scoped plan-index/ledger entries, proposed `docs/concepts/collection_selection.md`, and factual corrections to [effective tags](../../concepts/effective_tags.md)/[reaction records](../../concepts/reaction_record.md), whose behavior is unchanged. No application, test-suite, CI, schema, dependency or deployment changes. Retain failures/uncertainty; correct reports without erasing evidence. Runtime/deployment checks are inapplicable; QDB host capacity remains unverified.

## Key Risks

- **High risk:** Suggested selection changes could alter access, tags, pins, random distribution, ties or complete exports. Trace semantics and shared consumers before recommending a fix.
- Historical seeds may be repaired; source searches and LIMIT do not prove defects or efficiency. Verify current paths and separate examined/materialized/transferred/used counts.
- Fixtures/cache can distort ranking. Use isolated repeats, retain failures and host limits; unresolved evidence cannot support quantitative claims.

## Stage 1 — Coverage and seed reconciliation

**Estimate: 0.08**

- Goal: Establish coverage without duplicate findings.
- Dependencies: Approved Step 3; planning commit.
- Expected changes: Revision/environment, evidence conventions, family matrix, stable IDs; reconcile M02/M04/M09 with delivered fixes.
- Verification approach: Check prior reports, source ownership, family coverage and document integrity.
- Risks or open questions: Stale seeds; trace current callers before classification.
- Canonical components/API contracts touched: Application/RouteServices, ThreadRepository, prior audit/build-time reports; read-only.
- Estimate details: Owns baseline/conventions; uncertainty: historical overlap.

## Stage 2 — Public readers and consumers

**Estimate: 0.18**

- Goal: Identify unnecessary reading-path work.
- Dependencies: Stage 1.
- Expected changes: QDB welcome/list/search/random; standard board/tag/profile/directory; Forte, APIs, RSS and browser findings/probes.
- Verification approach: Trace eligibility/order/selection/output; compare counts, IDs/totals; check document integrity.
- Risks or open questions: Changed selection semantics; record invariants before recommendations.
- Canonical components/API contracts touched: Controllers/repositories, ActivityService, effective tags/reactions, pagination; read-only.
- Estimate details: Owns reader probes; uncertainty: indirect consumers.

## Stage 3 — Publication and maintenance

**Estimate: 0.18**

- Goal: Separate avoidable loading from necessary traversal.
- Dependencies: Stage 1; reuse Stage 2 findings.
- Expected changes: Offline publication/reader, rebuild/Git, static enumeration, import/export, scoring/queue classifications and probes.
- Verification approach: Trace selected records/dependencies, pins and complete exports; measure; check document integrity.
- Risks or open questions: Required full traversal; justify exceptions, mark untraced jobs unresolved.
- Canonical components/API contracts touched: PublicOfflineSnapshotBuilder/offline_reader, ReadModelBuilder, StaticArtifactBuilder, scripts/services; read-only.
- Estimate details: Owns maintenance probes; uncertainty: traversal versus hydration.

## Stage 4 — Ranked handoff

**Estimate: 0.11**

- Goal: Make independent fixes selectable.
- Dependencies: Stages 1–3, including failures/gaps.
- Expected changes: Ranked inventory, coverage/exceptions, concept updates, operator handoff, summary/ledger.
- Verification approach: Every recommendation has root ID, callers/sources, evidence status, smallest correction and parity/recovery checks; verify family dispositions and document integrity.
- Risks or open questions: Local/host mismatch; leave production savings and acceptance unverified.
- Canonical components/API contracts touched: Report/concept/ledger only; no detector or remediation.
- Estimate details: Owns consolidation; uncertainty: evidence confidence.

## Execution and evidence gates

After approval: new branch, organize cycle folder and repair links/index, commit plans before summary; commit each stage with its summary. Preserve unrelated drafts. Bound stages to about one hour each; replan overruns and record coverage gaps.

Stages 2/3: existing tools, disposable fixtures outside repository; fixed selection, 1000/10000 candidates where applicable, three fresh-process samples each. Exclude setup; record cache/order limits, counts/IDs, wall time, PHP peak and available RSS. Cap probes at 128 MiB/60 seconds; retain failures and stop scaling. No production probes.

Stage 4 host handoff: original `./v3 build-static` arguments, application/corpus revision, flags, PHP, phase timings, artifacts and activation outcome. Preserve working deployment and exact failures; no live recovery claim.

## Study checkpoint

2026-10-31 UTC: Step 2 approved, v1/B unchanged. Original baseline cfb8364f6db03cc09e27cd26afb93c109c723a08; planning context ade35a65. R1/P2 hashes verified; single Codex rater with prior audit/build-time exposure. Independent cycle 0.55 reaffirmed; stage originals 0.08/0.18/0.18/0.11 are non-additive, not hours. No execution, measured labor or accepted outcome; await Approved Step 3.

*— Codex · 2026-10-31 UTC*
