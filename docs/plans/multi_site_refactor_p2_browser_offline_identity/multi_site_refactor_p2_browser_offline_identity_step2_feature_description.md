# Multi-Site Refactor P2 Browser and Offline Identity — Step 2: Feature Description

> **Feature plan:** [Step 1](./multi_site_refactor_p2_browser_offline_identity_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p2_browser_offline_identity_step2_feature_description.md) · [Step 3](./multi_site_refactor_p2_browser_offline_identity_step3_development_plan.md) · [Step 4](./multi_site_refactor_p2_browser_offline_identity_step4_implementation_summary.md)

## Problem

Zenmemes-only browser and offline identifiers make profile switching unsafe on one origin and leave PWA identity ambiguous in published static artifacts.

## User Stories

- As a logged-in demo operator, I want to switch the active profile without losing my session or browser identity, or overwriting another profile's preferences or offline cache, so that demonstrations are fast and trustworthy.
- As a visitor, I want the installed/offline experience to identify the active profile so that cached content and diagnostics are intelligible.
- As a maintainer, I want dynamic and physical static delivery to use the same bounded profile runtime contract so that deployment does not silently change behavior.

## Core Requirements

- Derive theme and density preferences, cache identity, bootstrap validation, diagnostics, manifest identity, and worker registration from the validated profile browser namespace.
- Read a legacy Zenmemes preference once only when its namespaced replacement is absent; never copy that preference to Chouse or QDB.
- Retain/refresh only the active profile's cache and never delete a foreign profile cache.
- Support one active root-scope profile at a time on a shared origin, preserving the logged-in session and browser-held identity when the profile changes.
- Keep repository, database, identity, approval, content state, browser-held identity, and session state shared and instance-owned; never derive authentication keys from the profile namespace.

## Delivery Scope

- Work type: application change.
- Included outcome: profile-owned browser preferences and offline/PWA identity that work through dynamic pages and published physical artifacts.
- Excluded outcome: concurrent independent root-scope PWAs on one origin, a production profile-switcher UI, and changes to shared application or authentication state.

## Completion Boundary

- Normal entry: a logged-in visitor loads a profile, changes a preference, installs/refreshes the offline reader, or an operator switches the active profile for a demo.
- End-to-end outcome: only the active profile's namespaced preference and offline runtime are selected, the installed identity matches that profile, and the logged-in identity remains available after switching.
- Required recovery: missing namespaced Zenmemes preferences fall back once to the legacy value; stale matching caches refresh; foreign caches remain untouched.
- Release condition: a three-profile dynamic/static matrix verifies preference migration, manifest/worker identity, cache discovery/cleanup, and isolation.

## Risks

- **Cache cleanup removes another profile.** Impact: offline data loss. Earliest validation: cache matrix with matching, stale, and foreign names. Mitigation: one validated profile cache-prefix contract.
- **A physical artifact serves the wrong PWA identity.** Impact: incorrect installation/offline runtime. Earliest validation: inspect generated artifacts per profile. Mitigation: one shared dynamic/static delivery contract.
- **Legacy Zenmemes settings are lost.** Impact: preference regression. Earliest validation: first-load migration tests. Mitigation: read legacy keys only as an absent-namespaced-value fallback.
- **Demo switching implies unsupported concurrent workers.** Impact: confusing offline behavior. Earliest validation: root-scope registration test. Mitigation: document one-active-profile behavior and require separate origins/scopes for concurrent offline demos.
- **A profile switch loses authentication state.** Impact: the intended demo flow fails. Earliest validation: switch profiles while authenticated. Mitigation: keep authentication/session/browser-identity keys outside the profile-derived runtime contract.

## Shared Component Inventory

- **Profile descriptor:** reuse `SiteProfileRegistry` and its validated browser namespace as the sole identity input.
- **Authentication and browser identity:** reuse the existing shared session and browser-key contracts unchanged; they are explicitly not profile runtime inputs.
- **Shared layout and preference scripts:** extend the theme/density initialization and controls; do not introduce separate profile UI paths.
- **Offline runtime:** extend the existing registration, worker, health, and bootstrap surfaces with the same namespace contract.
- **Publication/runtime delivery:** extend the existing manifest, router, front controller, and static artifact builder so dynamic and physical artifacts agree.

## Simple User Flow

1. The active profile supplies its validated browser namespace.
2. The page restores that profile's preference, using the Zenmemes legacy fallback only when appropriate.
3. The profile-aware runtime registers or refreshes its matching offline cache and reports its identity in health diagnostics.
4. A profile switch preserves the logged-in identity and foreign browser/cache state; concurrent independent offline operation requires separate origins or scopes.

## Success Criteria

- Each profile exposes only its own derived preference, manifest, worker, and cache identity.
- A stored foreign preference/cache is ignored rather than adopted or deleted.
- A stale matching cache refreshes successfully under both dynamic and physical static delivery.
- An authenticated profile switch retains the existing session and browser-held identity.
- The existing shared-state boundaries remain unchanged.
