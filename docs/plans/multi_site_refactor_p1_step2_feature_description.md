# Multi-Site Refactor P1 — Step 2: Feature Description

> **Feature plan:** [Step 1](./multi_site_refactor_p1_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p1_step2_feature_description.md) · [Step 3](./multi_site_refactor_p1_step3_development_plan.md) · [Step 4](./multi_site_refactor_p1_step4_implementation_summary.md)

## Problem

QDB's complete user experience is split through generic forum layers, so classic QDB behavior is difficult to evolve, isolate, and prove absent from the other profiles.

## User Stories

- As a QDB visitor, I want every classic QDB URL and surface to work together so that browsing, searching, adding, and linking quotes feels like one product.
- As a visitor to another profile, I want QDB-only URLs rejected so that profile-specific behavior does not leak into the shared forum.
- As a QDB contributor, I want quotes to receive stable sequential numbers so that their displayed short links are easy to share and resolve.
- As a maintainer, I want QDB behavior to have one named boundary so that a future site can use presentation slots without inheriting QDB policy.

## Core Requirements

- The QDB profile retains all current classic routes: welcome, latest, top, leetness, add, random, search, numeric pagination, and short quote permalinks.
- Non-QDB profiles reject the QDB-only routes and numeric quote shortcuts while retaining their existing shared routes.
- QDB owns its navigation, welcome/search/random/add surfaces, board policy, card selection, pagination, and footer without changing shared forum data or identity behavior.
- One QDB quote-number capability supplies minting, parsing, lookup, and display-permalink behavior consistently from creation through viewing.
- Chouse remains on the P2 named-slot path; this slice does not introduce a general experience-plugin framework.

## Delivery Scope

- Work type: application change.
- Included outcome: a QDB-specialized experience and quote-number behavior that form one complete, profile-gated browse and contribution flow.
- Excluded outcome: Chouse specialization, generic plugin registration, presentation-slot work, browser/offline identity work, and changes to shared repository, database, identity, approval, or content ownership.

## Completion Boundary

- Normal entry: a visitor uses a classic QDB URL or creates a quote while the QDB profile is active.
- End-to-end outcome: QDB renders its specialized surfaces; a new quote receives a sequential display number; its numeric shortcut and card permalink resolve to the same quote.
- Required recovery: an unknown quote number and every QDB-only URL on another profile follow the normal not-found path without interfering with shared routes.
- Release condition: all existing classic QDB behavior remains available on QDB, is absent elsewhere, and quote-number behavior has one authoritative owner.

## Risks

- **A classic URL collides with a shared route.** Earliest validation: route matrix across all profiles. Mitigation before Step 3: list every retained QDB URL and its non-QDB result.
- **QDB presentation policy leaks into generic rendering.** Earliest validation: inspect the named experience boundary and non-QDB page output. Mitigation before Step 3: keep QDB selection explicit and retain generic data/rendering contracts.
- **Quote-number minting, lookup, and display disagree.** Earliest validation: create, resolve, and render quotes across the sequence boundary. Mitigation before Step 3: define one authoritative quote-number capability.
- **A framework is generalized prematurely for Chouse.** Earliest validation: review whether Chouse needs route or board policy beyond P2 slots. Mitigation before Step 3: constrain this slice to QDB and defer shared extraction until a second concrete specialized behavior exists.

## Shared Component Inventory

- **Profile selection:** reuse the canonical profile descriptor and its enabled-experience metadata to gate QDB behavior.
- **Forum rendering and data:** retain the existing generic board, compose, and page-rendering services for shared data and layout mechanics; add a named QDB experience only for QDB policy and surface selection.
- **QDB pages and quote cards:** extend the existing QDB welcome, random, search, add, and quote-card surfaces through named QDB presentation interfaces rather than generic profile-name branches.
- **Quote identifiers:** replace the current distributed QDB numbering behavior with one QDB quote-number capability used by creation, lookup, and display links.

## Simple User Flow

1. The QDB profile receives a classic QDB URL and selects the QDB experience.
2. The visitor browses or searches QDB surfaces, or opens Add Quote and submits a quote.
3. The quote receives its sequential QDB number and its card exposes the matching short permalink.
4. A visitor opens that numeric shortcut and reaches the same quote; unknown QDB numbers and all QDB-only URLs on other profiles return the normal not-found result.

## Success Criteria

- A route matrix confirms every current QDB classic URL works only for the QDB profile.
- QDB navigation, board/card/compose/footer surfaces are selected without generic QDB conditionals.
- New and imported QDB quotes have consistent number minting, lookup, display, and permalink behavior.
- Non-QDB profile pages and shared data behavior remain unchanged.
- The P1 design creates no generic experience framework and leaves Chouse for P2 presentation slots.
