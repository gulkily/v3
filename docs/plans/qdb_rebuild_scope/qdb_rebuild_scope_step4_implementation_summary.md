> **Feature plan:** [Step 1](./qdb_rebuild_scope_step1_solution_assessment.md) · [Step 2](./qdb_rebuild_scope_step2_feature_description.md) · [Step 3](./qdb_rebuild_scope_step3_development_plan.md) · Step 4

Debug mode: enabled
Effort study: railspike-effort / v3/qdb_rebuild_scope; [ledger](../effort_estimation_trial.md#cycle-v3qdb_rebuild_scope)
Extended estimates: enabled

**Cycle estimate: 0.85**

Entry: Step 3 approved, 2026-10-30 UTC; branch feature/bounded-tag-pages; planning commit 08dcb973. Original baseline 0392dd59c0646bebc37042455ecaccf825b13889, v1/B/R1/P2 and single exposed Codex rater retained. Step 3 projection 0.85 reaffirmed before implementation; original stage work S1–S6 0.20/0.12/0.12/0.18/0.15/0.14, non-additive. Scope and verification per approved plan. Measured labor unknown; production host acceptance pending. Unrelated draft audits and ledger entries excluded from commits.

*— Codex · 2026-10-30 UTC*

## Stage 1 - Live bounded tag pages

**Original estimate: 0.20**

**Retrospective stage work: 0.20**

- Changes: TagPage selects eligible IDs before hydrating 25 rows; controller/route/template use native page links. Schema 38 to 39, unchanged tables with new query contract and concept documentation.
- Verification: TagPaginationTest 2/2 (legacy membership parity, normal page/unknown tag, prior-version startup rebuilding before query); existing mixed-case tag smoke 1/1; isolated ./v3 rebuild succeeded through promotion. Initial fixture missed required reply_count; corrected test fixture and reran successfully. Whitespace checked.
- Notes: Original scope/estimate unchanged; traversal, serving, offline and resource acceptance belong to later stages. Production host unverified. No dependencies added.

*— Codex · 2026-10-30 UTC*

## Stage 2 - Complete traversal and recovery

**Original estimate: 0.12**

**Retrospective stage work: 0.12**

- Changes: Locked in the adapter/controller recovery contract with forward/back traversal, deleted anchors, changed activity, mixed-case cursor URLs, malformed/oversized/array/foreign-tag positions and empty-page restart tests. Stage 1 commit: fface7d9.
- Verification: TagPaginationTest 4/4; all 65 eligible roots traverse in 25/25/15 pages exactly once and reverse correctly. Invalid/exhausted positions show First page without silently showing initial results.
- Notes: Existing Stage 1 primitives satisfy these cases; no duplicate paginator added. Original scope/estimate unchanged; no production acceptance claimed.

*— Codex · 2026-10-30 UTC*
