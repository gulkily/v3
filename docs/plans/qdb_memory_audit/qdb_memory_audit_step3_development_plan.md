> **Feature plan:** [Step 1](./qdb_memory_audit_step1_solution_assessment.md) · [Step 2](./qdb_memory_audit_step2_feature_description.md) · [Step 3](./qdb_memory_audit_step3_development_plan.md) · [Step 4](./qdb_memory_audit_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/qdb_memory_audit; [P2 ledger](../effort_estimation_trial.md#cycle-v3qdb_memory_audit)
Extended estimates: enabled

**Cycle estimate: 0.40**

## Completion Contract

Approved scope: v2/A, documentation-only. Deliver verified M01–M10 findings and operator follow-ups. Allowed: cycle text/Markdown artifacts, its index/ledger entries and read-model/static-publication concept docs. Preserve measurements; no runtime, tests, CI, settings, dependencies or deployment changes.

After approval: branch, organize approved plans/debug under docs/plans/qdb_memory_audit/, then planning-only commit; each stage gets its summary-bearing commit. Exclude unrelated unapproved plans/ledger changes. Archive existing probe source verbatim as text; leave its executable unchanged and uncommitted.

Release after field, reference, ownership and scope checks. Correct stale claims or mark uncertainty. Runtime/UI/deployment checks are inapplicable; host recovery remains unverified.

## Key Risks

- **High risk:** False host-recovery claims: check provenance first; distinguish PHP 8.3 incidents from PHP 8.1 fixtures.
- Stale conclusions: check callers and reviewed revision before ranking.
- Scope leakage: verify staged paths and links against the intended committed tree.

## Stage 1

**Estimate: 0.15**

- Goal: Make the ten-finding inventory independently reviewable.
- Dependencies: Approved plan and planning commit.
- Expected changes: Reconcile required finding fields; preserve IDs/results; archive probe source as text with historical-command limitations.
- Verification approach: Trace ten findings to callers; classify legitimate traversals/bounded reads; compare archive bytes with the probe.
- Estimate details: Driver: cross-caller review; uncertainty: intervening source changes.
- Risks or open questions: Unsupported claims retain history but gain an explicit uncertainty label.
- Canonical components/API contracts touched: Rebuild, readers, evidence, offline/scoring/import documentation; runtime contracts unchanged.

## Stage 2

**Estimate: 0.10**

- Goal: Give the QDB operator actionable failure-to-follow-up guidance.
- Dependencies: Stage 1 inventory.
- Expected changes: Map reported failures/lock contention to fixes or follow-ups, retry guidance and retained evidence. Link M02/M04/M09 ownership; add concept entries.
- Verification approach: Walk recorded incidents through guidance; check fix revisions, host gaps, concept links and shared IDs.
- Estimate details: Driver: accurate recovery/ownership mapping; uncertainty: deployed revision remains unknown.
- Risks or open questions: No achieved-budget claims, host changes or live replay; retain actionable manual guidance.
- Canonical components/API contracts touched: build-static/rebuild/delete-record guidance, related audit boundaries; no runtime changes.

## Stage 3

**Estimate: 0.08**

- Goal: Publish a coherent documentation handoff.
- Dependencies: Stages 1–2.
- Expected changes: Finalize coverage, priorities, summary, index and ledger. Mark unpublished cross-cycle references pending.
- Verification approach: Check fields, committed-tree links, evidence, paths and whitespace; confirm zero runtime/dependency delta and explicit outstanding acceptance.
- Estimate details: Driver: shared document-integrity checks, owned here; uncertainty: pending cross-cycle links.
- Risks or open questions: Resolve dangling references/scope leakage before committing without broadening scope.
- Canonical components/API contracts touched: Audit documentation only.

## Concepts and study checkpoint

Concepts: Stage 2 adds read-model/static-publication entries; reaction-record entry unchanged. Step 2 approved. Step 3: 2026-10-30 UTC, v2/A, baseline cfb8364f6db03cc09e27cd26afb93c109c723a08, R1/P2; hashes reverified, single exposed Codex rater. Total 0.40 reaffirmed; S1/S2/S3 0.15/0.10/0.08 are non-additive stage work including verification/correction. Prior estimates preserved; no measured labor or accepted outcome. Await Approved Step 3.

*— Codex · 2026-10-30 UTC*
