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

## Stage 3 - Static and runtime serving

**Original estimate: 0.12**

**Retrospective stage work: 0.12**

- Changes: Added actual release/frontend tests for QDB and forum profiles; documented bounded tag artifacts. Existing query bypass and static enumeration already satisfy the contract, so no replacement routing introduced. Stage 2 commit: c076581a.
- Verification: 2/2 focused tests pass: fresh full release, mixed-case first route served statically, cursor route bypasses a sentinel first-page artifact, shared refresh retains tags, and existing failed-build recovery keeps the active release.
- Notes: Original scope/estimate unchanged. Publication is verified locally with fixtures; live host remains pending.

*— Codex · 2026-10-30 UTC*

## Stage 4 - Offline saved tag pages

**Original estimate: 0.18**

**Retrospective stage work: 0.18**

- Changes: Saved tag cards paginate in groups of 25 using native navigation classes, snapshot/tag-scoped positions, explicit foreign/stale/online recovery and popstate handling. Cache revision 14 to 15 delivers the updated reader without changing snapshot schema/generation. Stage 3 commit: 1f6ee8b5.
- Verification: Offline presentation/worker/profile tests 25/25 plus new saved-tag regression 1/1; 65 saved matches traverse and reverse, changed membership/generation/tag and malformed/online positions reject without rendering unrelated cards. JavaScript syntax passed.
- Notes: Original scope/estimate unchanged. Existing saved grouping remains; this stage bounds rendered cards, not snapshot generation. Full browser offline/reconnect evidence belongs to Stage 6.

*— Codex · 2026-10-30 UTC*

## Stage 5 - Memory and incident regression

**Original estimate: 0.15**

**Retrospective stage work: 0.15**

- Changes: Added isolated real-route/24-tag artifact resource fixtures and repeatable scaling/maximum-record tests. [Measurements](./qdb_rebuild_scope_memory_evidence.txt) retain dimensions and limitations. Stage 4 commit: 70a4f1b1.
- Verification: Resource test plus tags-index regressions and normal static-command smoke 4/4. All 1000/10000/40000 samples peak at 6 MiB under 32 MiB public/64 MiB artifact ceilings; exact-limit 16 MiB record plus 24 ordinary records peaks at 38 MiB under 128 MiB. Every tag artifact contains at most 25 cards. Initial fixture assertion assumed quote-card markup; corrected to the existing tag page's thread-card presentation and reran.
- Notes: Original scope/estimate unchanged. Hydration/output bounded; SQLite candidate examination not instrumented and may scale with corpus. Legacy index failure reproduced in predecessor fix; current index and first-tag paths both exercised. Live failing tag is unknown, production retry pending; no full-scale build capacity claim.

*— Codex · 2026-10-30 UTC*

## Stage 6 - Verification checkpoint before requested merge

**Original estimate: 0.14**

Status: In progress; no retrospective assessment or completed-cycle claim.
Request: User explicitly requested merge and push now, then continued testing. This authorizes an early integration checkpoint before Stage 6 completion; remaining verification and corrective work continue afterward.
Changes: Added real-browser fixture and pagination flow covering native online and service-worker offline navigation.
Verification so far: Browser online checks pass with JavaScript disabled/enabled, keyboard, history and failed-navigation retry; 16 themes at phone/desktop pass overflow and pagination stability checks. Real-worker offline check is still running. Stages 1–5 remain locally verified; production host recovery and full-suite results are not claimed.
Next: Complete offline/browser checks, fix findings, finish catalog/concepts/ledger and final focused regression evidence. Unrelated audit drafts remain outside integration.

*— Codex · 2026-10-30 UTC*
