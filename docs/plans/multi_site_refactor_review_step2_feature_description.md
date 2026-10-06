# Multi-Site Refactor Review — Step 2: Feature Description

> **Feature plan:** [Step 1](./multi_site_refactor_review_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_review_step2_feature_description.md) · [Step 3](./multi_site_refactor_review_step3_development_plan.md) · [Step 4](./multi_site_refactor_review_step4_implementation_summary.md)

## Problem

Site-specific behavior for Zenmemes, Chouse, and QDB is implemented through a mix of profile fields, direct name checks, specialized templates, and fixed browser assets. The next site design should declare intended behavior at stable seams instead of adding another set of scattered conditionals.

## User Stories

- As a maintainer, I want an evidence-backed inventory of site-specific conditionals so that refactors target the highest-coupling code first.
- As a future site designer, I want a clear profile-component contract so that I know what to configure, override, or inherit without modifying unrelated shared code.
- As an operator, I want presentation-specific behavior separated from shared instance state so that a site design does not fork content, identity, or approval behavior.

## Core Requirements

- Document every runtime Zenmemes, Chouse, and QDB conditional, grouped by responsibility and current owner.
- Define proposed profile seams for routes, navigation, board/card presentation, compose/write conventions, editorial content, themes, browser state, and offline/PWA assets.
- State which seams should be declarative profile metadata, bounded named overrides, or deliberately specialized modules.
- Specify the minimum contract and review checklist for adding a fourth site design while preserving shared instance data.
- Turn every recommended refactor into an implementation-ready slice with source/target ownership, dependency order, compatibility boundary, and verification contract.
- Produce documentation only; no application, asset, test, configuration, database, or deployment changes are in scope.

## Completion Boundary

- **Normal entry:** reviewer starts from the profile registry and traces each site-specific branch to its rendering, routing, write, or browser consumer.
- **End-to-end outcome:** a final componentization backlog maps every finding to a proposed owner, priority, first implementation slice, dependency, compatibility boundary, and future validation.
- **Required recovery:** ambiguous behavior, host-specific assumptions, and unresolved product choices are recorded as questions rather than resolved in code.
- **Release condition:** the review distinguishes current facts from recommendations and includes a new-site design checklist; implementation remains separately authorized work.

## Risks

- **Incomplete inventory hides a high-coupling conditional.** Earliest validation: search by site IDs and compare findings with profile/route/template/browser boundaries. Mitigation before Step 3: use a category-based inventory, not only text search.
- **Over-generalization makes QDB's intentional behavior harder to understand.** Earliest validation: classify every proposed seam as shared metadata, bounded override, or specialized module. Mitigation before Step 3: preserve QDB-specific modules where behavior is not reusable.
- **Pollyanna's layered overrides are copied too literally.** Earliest validation: compare its fallback and cache caveats with the current registry. Mitigation before Step 3: recommend explicit named slots only, never arbitrary stacked overrides.

## Shared Component Inventory

- **Profile/configuration:** `SiteProfileRegistry`, `SiteConfig`, `ThemeRegistry`, and state-path consumers; document the canonical contract and leaks.
- **Routing and navigation:** `Application`, `TemplateRenderer`, and shared navigation templates; identify route/menu capabilities versus site-name checks.
- **Board and writing:** `BoardPageController`, board/quote cards, compose templates, and `LocalWriteService`; identify generic forum versus QDB quote behavior.
- **Editorial presentation:** about/platform/busy-state content and branded CSS; recommend metadata or bounded profile-owned templates.
- **Browser and offline:** layout, theme/density scripts, registration, worker, manifest, health, router, and static artifacts; identify profile identity ownership.
- **Implementation planning:** existing profile, theme, route, board, and browser tests; specify the canonical verification surface for each future slice.

## User Flow

1. A maintainer selects a profile or plans a new site design.
2. The review identifies which shared capabilities that design uses and which named profile components it needs.
3. The maintainer follows the documented new-site checklist rather than adding direct site-name checks.
4. Future implementation validates the selected components without changing shared instance state.

## Success Criteria

- Every current site-specific conditional is accounted for in a componentization map, including its source location and proposed owner.
- The report defines a bounded contract for a fourth site: identity, enabled capabilities, named presentation slots, browser/offline namespace, and specialized-module criteria.
- Recommendations distinguish reusable capabilities from QDB-only behavior and identify Zenmemes hard-coding that should become profile data.
- Every recommended refactor has enough scope, ordering, compatibility, and verification detail to begin a separately authorized implementation plan.
- The final review contains only documentation changes.
