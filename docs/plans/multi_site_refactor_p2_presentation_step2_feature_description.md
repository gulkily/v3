# Multi-Site Refactor P2 Presentation — Step 2: Feature Description

> **Feature plan:** [Step 1](./multi_site_refactor_p2_presentation_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p2_presentation_step2_feature_description.md) · [Step 3](./multi_site_refactor_p2_presentation_step3_development_plan.md) · [Step 4](./multi_site_refactor_p2_presentation_step4_implementation_summary.md)

## Problem

Profile-specific presentation still relies on direct conditionals and embedded copy, making ordinary Zenmemes/Chouse variation and theme availability difficult to audit without turning every profile into a specialized experience.

## User Stories

- As a visitor, I want each profile to present its own registered navigation, cards, compose surface, editorial content, and theme choices so that the site feels coherent.
- As a maintainer, I want shared fallbacks for every presentation slot so that a missing selection remains usable.
- As a designer, I want branded themes available only where the profile permits them so that profile identity is intentional.
- As a developer, I want bounded slots instead of arbitrary file paths so that new variation stays reviewable and safe.

## Core Requirements

- Profiles select only registered navigation, board-card, compose, about, editorial, and branded-stylesheet slots; each slot has a shared fallback.
- Zenmemes/Boston editorial content, the Chouse about content, platform-document branding, and the Zenmemes busy message become profile-owned content or registered slots.
- Theme-menu availability derives from the active profile; the approved decision is that `chouse` and `qdb` branded themes are available only on their own profiles.
- QDB retains its specialized route and board behavior from P1; Chouse remains a declarative presentation selection.
- Arbitrary template paths, ordered stylesheet stacks, and CSS concatenation overrides are not supported.

## Delivery Scope

- Work type: application change.
- Included outcome: registered presentation selection, safe fallbacks, profile-owned editorial/chrome content, and profile-owned theme-menu availability across Zenmemes, Chouse, and QDB.
- Excluded outcome: QDB route/board policy, browser-storage/cache/manifest work, arbitrary user-selected templates, and shared repository, database, identity, approval, or content-state ownership.

## Completion Boundary

- Normal entry: a visitor loads a page under any active profile and opens the theme menu.
- End-to-end outcome: the page renders only its profile's registered slots/content and permitted themes, with shared fallbacks where a slot is unspecified.
- Required recovery: an absent, invalid, or unavailable slot/theme selection resolves to the registered shared/default presentation without loading an arbitrary asset.
- Release condition: all six slot families and named editorial/branding content are profile-selected; Chouse/QDB themes are unavailable outside their profiles.

## Risks

- **A missing slot breaks normal page rendering.** Earliest validation: render every slot under all profiles. Mitigation before Step 3: require shared fallbacks and registry validation.
- **Theme restrictions strand an existing preference.** Earliest validation: load an unavailable saved theme under each profile. Mitigation before Step 3: fall back to the profile default and test the menu contract.
- **P2 reimplements QDB behavior.** Earliest validation: review each QDB change against P1's module boundary. Mitigation before Step 3: allow P2 only to select registered presentation assets.

## Shared Component Inventory

- **Profile descriptor:** extend the canonical profile metadata with registered presentation selections and permitted theme choices.
- **Page/layout renderer:** extend the existing renderer to resolve registered slots and shared fallbacks; do not add a parallel rendering path.
- **Existing templates and editorial copy:** migrate the about page, platform documents, busy message, board card, compose surface, navigation, and branded stylesheet choices into the slot catalog.
- **Theme menu:** extend the canonical theme registry/menu to filter choices from the active profile rather than a fixed global list.

## Simple User Flow

1. A request selects a known profile or the Zenmemes fallback.
2. The renderer resolves that profile's registered presentation slots and permitted theme list.
3. The visitor sees the profile's navigation/content/chrome and may choose only its permitted themes.
4. An invalid or missing selection falls back to the shared/default slot without changing shared data behavior.

## Success Criteria

- A three-profile render matrix verifies every registered slot and fallback.
- Named editorial and branding content has no direct profile-name conditional in generic renderers.
- The theme menu exposes `chouse` and `qdb` only on their own profiles and safely recovers unavailable saved choices.
- No profile can select an unregistered template, stylesheet stack, or arbitrary asset path.
