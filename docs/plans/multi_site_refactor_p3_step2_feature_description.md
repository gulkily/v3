# Multi-Site Refactor P3 — Step 2: Feature Description

> **Feature plan:** [Step 1](./multi_site_refactor_p3_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p3_step2_feature_description.md) · [Step 3](./multi_site_refactor_p3_step3_development_plan.md) · [Step 4](./multi_site_refactor_p3_step4_implementation_summary.md)

## Problem

The declarative multi-site contract is covered by tests that still encode site names and partial expectations, so a contract change or fourth profile can regress behavior without one descriptor-derived proof. This is internal maintenance that protects the normal profile-selection flow; it does not change product behavior.

## User Stories

- As a maintainer, I want regression expectations to come from the registered profile contract so that profile behavior can evolve without scattered site-specific test branches.
- As a future site author, I want a fourth profile fixture to pass the same contract so that I can identify missing descriptor capabilities before shipping a new design.
- As a release operator, I want profile selection validated through dynamic and static browser/offline delivery so that profile-scoped assets do not leak across sites.

## Core Requirements

- Derive profile, experience, and presentation expectations from the canonical contract rather than fixed Zenmemes, Chouse, or QDB matrices.
- Cover all three registered profiles for route availability, navigation/card/chrome selection, browser namespace and PWA/cache identity, static-output path, and shared instance state.
- Run a fourth-site fixture through the same regression contract without a fixture-specific assertion branch.
- Preserve the existing default-profile fallback and shared repository, database, session, and browser-held identity boundaries.
- Mark each P3 checklist item complete only after its corresponding verification passes; do not revise completed P2 evidence.

## Delivery Scope

- Work type: application change — regression tests/test support plus the P3 checklist status update.
- Out of scope: new production site designs, application behavior changes, schema changes, and changes to completed P0–P2 requirements.

## Completion Boundary

- Normal entry: a registered profile is selected through the existing configuration path.
- End-to-end outcome: the same descriptor-derived contract verifies each registered profile and a fourth-site fixture across the listed presentation, runtime, static-output, and shared-state concerns.
- Recovery: an absent or unknown profile still takes the existing default-profile fallback; invalid fixture data fails through existing descriptor validation rather than a site-specific workaround.
- Release condition: targeted matrix and relevant full-suite verification pass, and the three P3 checklist entries are updated with their verified completion state.

## Risks

- Descriptor fields may not express an expectation needed by an existing test. Impact: a misleading derived matrix. Earliest validation: inventory every current fixed expectation. Mitigation: add only a reusable named contract where needed; do not infer from a site name.
- The fourth fixture may expose an untestable registry or presentation dependency. Impact: bespoke fixture logic. Earliest validation: run it through the matrix before migrating every existing assertion. Mitigation: use existing validation and shared slots/experiences; stop for replanning if product behavior is required.
- Dynamic worker state or static artifacts may contaminate another profile's test. Impact: false isolation results. Earliest validation: run per-profile runtime and static checks in isolated fixtures. Mitigation: retain existing profile-scoped cleanup and assert shared-state boundaries.
- Checklist status could overstate coverage. Impact: an incomplete release gate. Earliest validation: map each checkbox to its test evidence. Mitigation: update only after the matching verification passes.

## Shared Component Inventory

- `SiteProfileRegistry` and `PresentationSlotRegistry`: reuse as the canonical expectation source; extend no site-specific test data.
- Existing experience routing and shared layout surfaces (navigation, cards, compose, about, and chrome): reuse unchanged; verify their selected registered components.
- `BrowserRuntimeProfile`, manifest/worker delivery, and offline worker lifecycle: reuse unchanged; verify profile-derived browser identity and cache isolation.
- `PresentationPathResolver` and static-artifact publication: reuse unchanged; verify the registered profile's output root and rendered runtime assets.
- Existing smoke/matrix fixtures and shared repository/session state: extend the test contract only; add no production UI or API surface.

## Simple User Flow

1. An operator selects a registered site profile through the existing configuration path.
2. The application resolves its experience, presentation, browser runtime, and static-output identity from that profile.
3. The regression matrix verifies those results and shared-state boundaries for every registered profile.
4. A fourth-site fixture follows the identical matrix and exposes any missing reusable contract.
5. Passing evidence updates the three P3 checklist entries.

## Success Criteria

- No P3 matrix expectation requires a branch on a specific existing site name.
- All three registered profiles pass one matrix covering every P3 checklist concern.
- A fourth-site fixture passes that same matrix without bespoke assertions or production-site behavior.
- The P3 checklist reflects only verified completed work, while P2 completion evidence remains intact.
