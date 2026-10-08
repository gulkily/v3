# Multi-Site Refactor Review — Step 3: Development Plan

> **Feature plan:** [Step 1](./multi_site_refactor_review_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_review_step2_feature_description.md) · [Step 3](./multi_site_refactor_review_step3_development_plan.md) · [Step 4](./multi_site_refactor_review_step4_implementation_summary.md)

## Completion Contract

- Normal entry: trace every current site-specific branch from profile selection through its consumers.
- End-to-end outcome: a documentation-only report groups findings into profile components, metadata, named overrides, and specialized modules, then turns each recommendation into an ordered implementation slice.
- Required recovery: unresolved behavior, product choices, and host assumptions remain explicit questions for a future implementation plan.
- Deployment/external verification: report which findings are source-confirmed and which require future operator validation.
- Release condition: a fourth-site design contract and implementation-ready, prioritized refactor backlog are documented; no code or runtime state changes occur.

## Key Risks

- **High risk: text search alone misses implicit site coupling.** Early validation: review registry, routes, renderers, templates, write paths, browser assets, and publication paths by category. Mitigation: record evidence source for each finding.
- **High risk: generic abstractions erase intentional QDB differences.** Early validation: assess each QDB branch for reuse beyond QDB. Mitigation: recommend a specialized module when no shared capability exists.
- **High risk: layered overrides obscure ownership.** Early validation: compare with Pollyanna's precedence/caching caveats. Mitigation: recommend only explicit named override slots with shared fallbacks.

## Stage 1

- Goal: create the complete current-state conditional inventory.
- Dependencies: revised Step 2 approval and Step 1 checklist.
- Expected changes: document direct site checks, fixed Zenmemes identifiers, profile-specific templates/assets, and implicit profile coupling by source path and category; no code changes.
- Verification approach: cross-check repository-wide site-ID searches against route, render, write, browser, and publication entry points.
- Risks or open questions: Impact: omitted findings distort priority; early validation: reconcile categories with the Step 1 checklist; mitigation: label unknown ownership explicitly.
- Canonical components/API contracts touched: profile registry/config, application routes, controllers, renderers, templates, browser assets, publication scripts.

## Stage 2

- Goal: define the target componentization map for all findings.
- Dependencies: Stage 1 inventory.
- Expected changes: classify each finding as declarative profile metadata, a named profile presentation slot, a reusable capability, or a specialized site module; document owner, rationale, first refactor slice, dependency, compatibility boundary, and verification surface; no code changes.
- Verification approach: ensure every direct site-name conditional receives a disposition, implementation order, and test/recovery contract; ensure no proposed component mixes presentation with repository/database/approval state.
- Risks or open questions: Impact: a new abstraction can add indirection without reuse; early validation: require a plausible second consumer for reusable capabilities; mitigation: retain one-off behavior in a named specialized module.
- Canonical components/API contracts touched: `SiteProfileRegistry`, `ThemeRegistry`, routing, navigation, board/card, compose/write, and offline boundaries.

## Stage 3

- Goal: derive the fourth-site design contract and override rules.
- Dependencies: Stage 2 componentization map and Pollyanna comparison.
- Expected changes: document required profile declarations, optional capabilities, bounded template/asset slots, browser/offline identity, specialized-module criteria, and a design-review checklist; no code changes.
- Verification approach: walk Zenmemes, Chouse, and QDB through the contract and identify any field that cannot explain their intended behavior.
- Risks or open questions: Impact: unbounded overrides recreate scattered conditionals; early validation: test every proposed slot against a named shared fallback; mitigation: prohibit arbitrary overrides and theme stacking.
- Canonical components/API contracts touched: profile contract, renderer/template boundary, route/module boundary, static/browser asset boundary.

## Stage 4

- Goal: publish the final documentation-only code-review report and prioritized future backlog.
- Dependencies: Stages 1–3.
- Expected changes: create the Step 4 summary with evidence table, refactor candidates, recommended sequencing, per-slice completion contracts, compatibility/migration notes, test matrix, and out-of-scope decisions; no code changes.
- Verification approach: confirm every recommendation cites a reviewed path, can be started without rediscovering its dependencies, and the final diff contains only planning/review documents.
- Risks or open questions: Impact: recommendations may be mistaken for authorization; early validation: state that implementation needs a separate approved feature; mitigation: keep future changes out of this review artifact.
- Canonical components/API contracts touched: planning/review documents and read-only code evidence only.
