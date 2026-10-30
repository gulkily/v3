> **Feature plan:** [Step 1](./qdb_rebuild_scope_step1_solution_assessment.md) · [Step 2](./qdb_rebuild_scope_step2_feature_description.md) · Step 3 · [Step 4](./qdb_rebuild_scope_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/qdb_rebuild_scope; [P2 ledger](../effort_estimation_trial.md#cycle-v3qdb_rebuild_scope)
Extended estimates: enabled

**Cycle estimate: 0.85**

## Completion Contract

Deliver the catalog and 25-item tag pages on every site, preserving complete native traversal, news, cards/actions and URLs; publish first pages and recover correctly through runtime/offline routes. Meet Step 2's 32/64 MiB scaling and separate 128 MiB large-record checks. No feature removal, new dependency, optional-prebuild policy or snapshot-generation redesign. Local completion requires all stage checks; production recovery requires the operator's original PHP 8.3 build-static run through activation and published-page checks. Without host access, provide exact retry guidance and keep host acceptance pending.

## Key Risks

- **High risk:** Wrong membership/order loses content. Stage 1 compares old/new selection, including malformed tag data; Stage 2 proves full traversal before dependent serving work.
- **High risk:** Static/offline fallback can show unrelated results. FrontController already bypasses query-bearing artifacts; offline currently ignores tag positions. Stage 3 proves frontend routing; Stage 4 adds distinct saved-position context and explicit online-position recovery.
- **High risk:** Large records and other build phases may still exceed memory. Stage 5 separates per-tag proof from full-build acceptance; any failed approved budget blocks completion, never licenses truncation or a raised limit.

## Stage 1 — Live bounded tag pages

**Estimate: 0.20**

- Goal/dependencies: First native page and next link; none.
- Changes: Add tag page adapter `page(PDO, string, ?array, string): array`; filter/deduplicate before selecting IDs, hydrate only 25 rows, wire controller/query/template. Bump current schema version, document unchanged tables/new query contract and startup recovery.
- Verify: Mixed tags/labels/case, hidden/retracted roots, unknown tags, ordering/ties, first-page cards/actions; prior-version startup and isolated ./v3 rebuild.
- Risk: Selection drift; compare legacy membership on small fixtures before wiring. No new persisted tag projection unless replanned.
- Contracts: ThreadRepository/new tag adapter, TagsPageController, Application, PageNavigation, tag.php, schema metadata/spec.
- Estimate details: Query parity and normal-route wiring; uncertainty: malformed JSON compatibility.

## Stage 2 — Complete traversal and recovery

**Estimate: 0.12**

- Goal/dependencies: Reach every result and return safely; Stage 1.
- Changes: Validate tag-bound cursor context and activity/ID boundaries; implement previous direction, invalid-position First page and empty-page recovery.
- Verify: Forward/backward traversal without gaps/duplicates, ties, deletions/arrivals, rebuilt row identities, malformed/oversized/array/cross-tag positions, case aliases and news.
- Risk: Lost/duplicated results; prove unchanged-data traversal before publication tests.
- Contracts: Tag adapter, PageNavigation, shared navigation partial and controller.
- Estimate details: Boundary/recovery matrix; uncertainty: concurrent list changes, not snapshot consistency.

## Stage 3 — Static and runtime serving

**Estimate: 0.12**

- Goal/dependencies: Publish bounded first pages with functioning later links; Stage 2.
- Changes: Integrate existing tag enumeration and artifact path rules; retain query bypass, refresh/invalidation and candidate recovery. Change routing only where tests demonstrate a gap.
- Verify: Actual FrontController first/later pages, fresh full build and shared refresh, mixed-case URLs, failed candidate preserving prior release, compatible QDB and forum profiles.
- Risk: Stale first-page interception; seed conflicting static HTML and prove cursor requests bypass it.
- Contracts: StaticArtifactBuilder/ReleasePublisher, FrontController, invalidator and serving tests.
- Estimate details: Publication integration; uncertainty: existing stale-artifact behavior.

## Stage 4 — Offline saved tag pages

**Estimate: 0.18**

- Goal/dependencies: Bounded traversal of saved content with honest recovery; Stage 2, integrate after Stage 3.
- Changes: Extend existing offline tag renderer with 25-card pages, saved-snapshot-bound positions and history handling. Online/foreign/stale positions offer saved start or reconnect, never silently map to page one; keep missing-tag feedback.
- Verify: More than 25 saved matches, Previous/Next/Back/reload, changed snapshots, unknown tags, network failure from online cursor URLs and reconnect.
- Risk: Online/saved contexts conflated; establish separate contexts before renderer wiring.
- Contracts: offline_reader.js, service_worker.js routing, existing offline tests; no snapshot generator/schema redesign.
- Estimate details: Browser recovery/state wiring; uncertainty: existing offline navigation test harness.

## Stage 5 — Memory and incident regression

**Estimate: 0.15**

- Goal/dependencies: Demonstrate bounded input/output; Stages 1–4.
- Changes: Add isolated route/artifact memory regressions and bounded fixtures; replay index and first-tag failure mechanisms. Record PHP peak/time and inspected-versus-hydrated counts separately.
- Verify: 1000/10000/40000 threads, 2 KiB bodies/1 KiB previews, fixed 25-result pages and 24 populated tags; 32 MiB public/64 MiB tag generation, at most 4 MiB peak growth. Separately test maximum-valid-record rendering at 128 MiB.
- Risk: Other failures mask tag evidence; isolate tag measurement, then run normal small-fixture build; report full production capacity separately. Failed budgets block closeout.
- Contracts: Application, static route generation and subprocess test harness.
- Estimate details: Reproducibility/resource checks; uncertainty: maximum-record rendering overhead.

## Stage 6 — UI, catalog and operator handoff

**Estimate: 0.14**

- Goal/dependencies: Verified reader flow and actionable QDB retry; Stages 1–5.
- Changes: Finalize catalog, concepts, implementation summary and cycle ledger. Record all-site confirmation, evidence and remaining optional-prebuild work.
- Verify: Phone/desktop, supported affected themes, no-JS online navigation, keyboard, long content/overflow, empty/error/loaded states and stable first paint. Run focused shared-reader/QDB/static/offline checks; inspect dependency delta and documentation links.
- Risk: Local success mistaken for host recovery; provide original build-static/settings retry, all-four-phase and published-news/tag checks, retain exact next failure. Host execution/deployment requires separate access/authority.
- Contracts: Shared pagination/cards, UI verification guidance, catalog; read-model/static-publication concepts plus new tag-browsing concept. This stage owns cross-stage browser evidence and closeout bookkeeping.
- Estimate details: Cross-profile UI and handoff; uncertainty: unavailable production host.

## Execution and study checkpoint

Step 2 approved, 2026-10-30 UTC; user explicitly accepts all-site effects, an in-scope confirmation of shared tag behavior. No change to main board/Forte listing contracts. Before implementation: create a feature branch, organize this cycle under docs/plans/qdb_rebuild_scope/, update links/index, and commit only approved planning/organization files. Create the summary afterward; each completed stage gets its own summary-bearing commit. Other draft audits and their ledger entries stay outside these commits; scope the ledger closeout to this cycle.

Six stages target about one hour each; stop and replan any oversized stage or failed dependency. Concepts and ledger are within the approved scope. R1/P2, v1/B, baseline 0392dd59c0646bebc37042455ecaccf825b13889 and single exposed Codex rater retained; hashes reverified. Whole-cycle 0.85 reaffirmed independently of non-additive stage-work estimates 0.20/0.12/0.12/0.18/0.15/0.14. No measured labor, implementation, host acceptance or accepted outcome. Await Approved Step 3.

*— Codex · 2026-10-30 UTC*
