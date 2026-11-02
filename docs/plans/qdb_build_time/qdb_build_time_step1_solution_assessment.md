> **Feature plan:** Step 1 · [Step 2](./qdb_build_time_step2_feature_description.md) · [Step 3](./qdb_build_time_step3_development_plan.md) · [Step 4](./qdb_build_time_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/qdb_build_time; [P2 ledger](../effort_estimation_trial.md#cycle-v3qdb_build_time)
Extended estimates: enabled

**Cycle estimate: 1.25**

## Original Query

It worked, and it took a very long time. We should try to shave that down.

```text
[1/4] Read model: index commits complete (39160.5 ms).
[1/4] Read model: index activity evidence complete (44503.0 ms).
[2/4] Rendering profile pages (675/5215).
Static artifacts: 5253 HTML files, 53 asset files
Built and activated static HTML release /home/qdb/v3/state/static_html/releases/release-20261030201011-af62aa46
Elapsed: 314.961 seconds
real    5m15.077s
user    1m39.683s
sys     2m48.179s
```

## Understood Intent

Reduce repeat-build wall time after the successful QDB memory fix, preserving content, accessible profiles and correct trust/history behavior. This is a distinct follow-up; prior all-site tag approval does not automatically change all sites' publication defaults.

Problem: Full build spends 83.6635 seconds in two history phases and eagerly generates 5215 profile pages; profile-phase duration is not supplied.

Concepts touched: [Static publication](../../concepts/static_publication.md) changed in A/C; [read-model rebuild](../../concepts/read_model_rebuild.md) used unchanged in A, optimized in B/C; profile delivery proposed concept. [Source findings and timing baseline](./qdb_build_time_findings.txt).

## Option A — Optional profile prebuilding first

**Estimate: 0.50**

Add an explicit, narrowly scoped profile-prebuild setting, allowing QDB to generate profiles on request while preserving current defaults elsewhere and an opt-in for static-only deployments. Measure before/after build cost and profile request cost on the same fixture.

- Independently useful slice: avoids thousands of artifact renders/writes without removing profiles or their attribution data. Ordinary routes, access controls, new-release cleanup, missing/stale artifact behavior and rollback need verification.
- Exact seconds saved unknown. Configure QDB only through an explicit operator action; do not silently widen the existing thread/post flag or change every site's default.

## Option B — Faster history derivation first

**Estimate: 0.90**

Preserve full commit and evidence results while reducing unnecessary Git diff work and repeated manifest reads across bounded activity batches. Compare derived output and phase timings on representative history fixtures.

- Directly addresses the measured 39.2 + 44.5 seconds; does not remove profile generation. Cold/warm cache, large commits, renames/merges, missing history and signature evidence complicate parity and memory bounds.
- No promise to eliminate all 83.7 seconds; no skipped trust derivation or unbounded cache.

## Option C — Both in one delivery

**Estimate: 1.25**

Combine optional profile artifacts and history optimizations with distinct before/after measurements.

- Selected by the user. Keep separate measurement and parity gates for profile delivery, commit indexing and activity evidence. The 1.25 estimate warrants a split discussion; it does not itself mandate separate cycles. Step 3 must still fit the normal stage/day limit or propose a split before implementation.

## Selected direction and boundary

User selected C: optional profile prebuilding plus faster commit indexing and activity evidence derivation in one delivery. Preserve complete history/evidence results, memory bounds, rename/merge semantics, missing-history recovery and signature/attribution behavior. Measure the three changes separately against the same baseline so their contributions remain distinguishable. Success requires ordinary profile links still working with correct authorization and no stale artifacts, full build/activation and retry recovery, a measurable reduction in generated files, and measured build/request costs. Full static-only export retains opt-in prebuilding. No dependency additions, canonical changes, deletion of identity/approval/history data, limit increases, deployment or server access implied. Baseline sys CPU suggests kernel/process/filesystem work but does not establish disk-wait time or its cause.

## Study checkpoint

Intake/Step 1: 2026-10-30 UTC; v1/A; baseline 4abda3dbd144370a3353781b29e8690e46d47d88; R1/P2 standing enrollment. Codex single exposed rater after previous audits/fixes and initial source inspection; pristine pre-inspection intake missing. Total 0.50 reaffirmed at handoff; B 0.90/C 1.25 are non-additive complete-cycle alternatives. Three frozen R1 hashes verified. No implementation, measured labor, local timing campaign or accepted outcome; await Approved Step 1.

*— Codex · 2026-10-30 UTC*

## Selection revision — 2026-10-30 UTC

Request: Choose Option C.
Disposition: Selected combined scope v2/C; original v1/A recommendation and 0.50 checkpoint retained above. Current whole-cycle projection 1.25 reaffirmed from the existing C alternative; this is a scope selection, not measured effort or the sum of A and B. Same baseline/R1/P2 and single exposed rater. User prefers the combined delivery after the split recommendation; retain stage sizing and replan if it cannot fit. Selection does not approve Step 1 or implementation; await Approved Step 1.

*— Codex · 2026-10-30 UTC*

Approval update: 2026-10-30 UTC. User approved Step 1 for selected Option C. Step 2 defines the combined requirements; earlier selection and score snapshots remain intact.
