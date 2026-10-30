> **Feature plan:** Step 1 · [Step 2](./qdb_rebuild_scope_step2_feature_description.md) · [Step 3](./qdb_rebuild_scope_step3_development_plan.md) · [Step 4](./qdb_rebuild_scope_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/qdb_rebuild_scope; [P2 ledger](../effort_estimation_trial.md#cycle-v3qdb_rebuild_scope)
Extended estimates: enabled

**Cycle estimate: 0.85**

## Original Query

Can we catalog features that aren't exposed in QDB's main interface and somehow minimize their impact on rebuild?

## Understood Intent

Identify unnecessary QDB build work while preserving supported functionality. Subsequent incident: phase 2 reports `Rendering tag pages (0/24)` then exhausts 128 MiB in `templates/pages/tag.php:10`. Prioritize that failure; the earlier index fix does not bound per-tag content or HTML.

Problem: A QDB presentation profile still uses shared derivation and publication, and absence from navigation does not distinguish optional prebuilding from required data.

Concepts touched: [Read-model rebuild](../../concepts/read_model_rebuild.md) used unchanged in B; [static publication](../../concepts/static_publication.md) changed; tag browsing proposed concept. Initial [feature/cost catalog](./qdb_rebuild_scope_catalog.txt) is source-backed, not a measured performance report.

## Option A — Catalog and prioritize only

**Estimate: 0.30**

Complete the exposure/dependency inventory and rank independently useful fixes.

- Lowest behavioral risk; does not resolve the current tag-page failure.

## Option B — Catalog plus bounded tag browsing first

**Estimate: 0.85**

Deliver the catalog and a first working optimization: bounded tag selection and HTML pages, preserving every result through native pagination, including news, static serving and runtime recovery.

- Recommended: directly addresses the reported blocker without removing reachable features. Shared pagination, query/schema compatibility, offline behavior and browser verification drive the work.
- Retains full core derivation. Optional profile-based HTML prebuilding is the next separately approved slice, informed by the catalog. This does not promise the rest of the production build fits 128 MiB.

## Option C — Catalog plus profile-aware publication

**Estimate: 1.15**

Choose QDB's eager HTML/artifact set explicitly and serve secondary features on demand, including correcting unbounded tag pages so fallback requests work.

- Broader rebuild savings, but needs profile-switch, stale-artifact, offline and static-only deployment decisions. Split tag browsing and publication policy into separate cycles before implementation.
- Selective omission of identity, reaction, activity or trust derivation remains a separate investigation, not an implied capability of this option.

## Recommendation and boundary

Choose B first, then assess optional prebuilding. The catalog identifies direct/conditional exposure: `/tags/news` is linked from Welcome; Messages can appear for approved members. Preserve canonical records, vote/flag semantics, existing URLs and authorization. No new dependencies, raised memory limit, production setting changes or deployment. Apply CONTRIBUTING's schema/version requirements to changed core queries. Stage 2 must define pagination/recovery and measurable memory acceptance; this assessment does not implement it.

Request disposition: The tag-page fatal is incorporated as the immediate priority; the existing broader capacity and load-before-selection audits remain separate and pending. M02/M04 overlaps retain their original finding ownership.

## Study checkpoint

Intake/Step 1: 2026-10-30 UTC, v1/B, baseline 0392dd59c0646bebc37042455ecaccf825b13889, R1/P2 standing enrollment. Codex single exposed rater; prior audit/fix and initial source inspection precede this checkpoint, so pristine prospective intake is missing. Total 0.85 reaffirmed at handoff; A 0.30/C 1.15 are non-additive whole-cycle alternatives. All three host-local frozen R1 hashes verified. No measured labor, independent rating, implemented optimization or accepted outcome. Planning remains uncommitted; await Approved Step 1.

*— Codex · 2026-10-30 UTC*

Approval update: 2026-10-30 UTC. User approved Step 1; recommended Option B advances to Step 2. Historical estimates and intake exposure above remain unchanged.
