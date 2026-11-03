> **Feature plan:** [Step 1](./rebuild_memory_bound_step1_solution_assessment.md) · Step 2 (not started) · Step 3 (not started) · Step 4 (not started)

Debug mode: enabled (project marker)
Effort study: railspike-effort / v3/rebuild_memory_bound; [P2 ledger](effort_estimation_trial.md#cycle-v3rebuild_memory_bound)
Extended estimates: enabled

**Cycle estimate: 1.00**

## Original Query

Please write Step 1 for each.

## Understood Intent

Assess the post-heavy rebuild failure from the [capacity study](application_capacity_study/capacity_report.txt), preserving derivation, validation and recovery rather than raising limits or deleting records.

## Problem

Normal rebuild retains whole post collections, uses 108 MiB at 10000 ordinary records and exhausts 128 MiB at 40000 before candidate promotion.

## Option A — Bound retention throughout post ingestion (Recommended)

**Estimate: 1.00**

Process the complete source with bounded retained content and disposable disk-backed ordering where necessary, preserving the existing derived result and candidate lifecycle.

- Pros: Addresses accumulation rather than moving its peak; retains complete history/content and deterministic ordering.
- Cons: More temporary I/O and careful cleanup; ordering, reply aggregation and trust parity span shared rebuild consumers.
- Driver/uncertainty: Coupled post passes and later phases; scope is near the comfortable cycle ceiling.

## Option B — Remove duplicate retention in the current rebuild

**Estimate: 0.65**

Keep the present ingest/order structure while reducing duplicated content and releasing collections sooner.

- Pros: Smaller behavioral change; simpler parity review and lower ordinary-case peak.
- Cons: Remaining corpus-sized collections still grow; may not complete 40000 records at 128 MiB.
- Driver/uncertainty: Whether the reduced peak clears the entire ordinary rebuild, not merely its first failing allocation.

## Recommendation and boundary

Choose A for the measured post-heavy slice: normal rebuild must finish validation/promotion at 1000/10000/40000 with exact record/order/trust parity; interruption retains the old model and retry completes. Cover large individual records and reply trees separately. This slice ends at validated promotion and normal post/API reads. Full QDB release activation is a combined host-cycle gate after the selection fix; rebuild success alone does not establish it. Use 48/64 MiB as ordinary maintenance targets without promising them for every valid record distribution. If later required phases or eight-stage/day feasibility exceed this slice, replan before dependent work; clearing one allocation is not completion.

Reuse: `src/ForumRewrite/ReadModel/{ReadModelBuilder,ReadModelCandidateBuilder,ReadModelCandidatePromoter}.php`, existing history-evidence machinery and `tests/ReadModelCandidateBuilderTest.php`; verify CLI and write/import/queue consumers. Preserve current retraction semantics and the [handoff](retraction_simplification_handoff.md); no new publication guards or history purge.

Concepts touched: [rebuild](../concepts/read_model_rebuild.md) changed; [history evidence](../concepts/history_evidence_indexing.md) and [publication](../concepts/static_publication.md) used unchanged. Zero proposed dependencies; any core read-model change includes prescribed schema/startup verification. New PHP 8.3 acceptance belongs to the separate host cycle.

## Study checkpoint and approval

2026-11-01 UTC; scope v1/A; baseline 75b5e30eb40b16f6297f69d83214b4e4926499b2; R1/P2 standing enrollment, all frozen hashes verified. First recorded intake after prior study/source exposure: 1.00; Step 1 handoff reaffirms 1.00. Codex single exposed rater; pristine pre-inspection intake unavailable, not reconstructed. Options estimate complete scoped cycles, not hours or additive forecasts. Separate follow-up to v3/application_capacity_study, not a duplicate study outcome. Plan only; no implementation, host execution, commits or accepted outcome. Ledger/debug updates stay uncommitted. Await Approved Step 1 for this cycle; future branch reuse is not assumed.

*— Codex · 2026-11-01 UTC*
