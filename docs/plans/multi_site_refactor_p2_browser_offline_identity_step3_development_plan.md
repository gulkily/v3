# Multi-Site Refactor P2 Browser and Offline Identity — Step 3: Development Plan

> **Feature plan:** [Step 1](./multi_site_refactor_p2_browser_offline_identity_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p2_browser_offline_identity_step2_feature_description.md) · [Step 3](./multi_site_refactor_p2_browser_offline_identity_step3_development_plan.md) · [Step 4](./multi_site_refactor_p2_browser_offline_identity_step4_implementation_summary.md)

## Completion Contract

- Normal entry: an authenticated operator changes the active server-selected profile, or a visitor restores preferences or installs/refreshes offline reading.
- End-to-end outcome: the active profile alone supplies derived preference, manifest, worker, diagnostic, and cache identity; the existing logged-in session and browser-held identity remain intact.
- Required recovery: an absent namespaced Zenmemes preference reads its legacy value; stale matching caches refresh; foreign-profile preferences and caches are neither adopted nor deleted.
- Deployment/external verification: exercise dynamic routes and generated physical artifacts for Zenmemes, Chouse, and QDB, including direct worker/manifest delivery.
- Release condition: all four P2 browser/offline checklist entries pass the three-profile matrix; no production profile-switcher UI or authentication contract changes are introduced.

## Key Risks

- **High risk: foreign cache cleanup loses offline data.** Early validation: simulated matching, stale, and foreign cache names. Mitigation: a validated exact cache-prefix contract with owned-cache cleanup only.
- **High risk: published artifacts identify the wrong profile.** Early validation: inspect per-profile worker and manifest output before deployment. Mitigation: one runtime descriptor drives dynamic and static delivery.
- **High risk: switching profile ends the demo session.** Early validation: change the active profile while authenticated. Mitigation: keep session and browser-identity keys outside profile-derived state.

## Stage 1

- Goal: expose one validated browser-runtime descriptor per site profile.
- Dependencies: approved Step 2; existing `SiteProfileRegistry` browser namespaces.
- Expected changes: add a closed descriptor/derivation contract for preference keys, cache identity, manifest identity, and worker bootstrap identity, with legacy Zenmemes identifiers represented only as migration inputs.
- Verification approach: unit-test all three descriptors, invalid/duplicate namespaces, and the absence of authentication/session inputs.
- Risks or open questions:
  - Impact: inconsistent consumers produce cross-profile state.
  - Early warning / validation: assert exact derived values from one registry source.
  - Mitigation: prohibit consumer-owned literal profile identifiers.
- Canonical components/API contracts touched: `SiteProfileRegistry`; browser-runtime descriptor/derivation API.

## Stage 2

- Goal: isolate theme and density preferences without disturbing browser identity.
- Dependencies: Stage 1 descriptor.
- Expected changes: replace Zenmemes-only layout and toggle storage access with derived preference keys; add absent-key-only Zenmemes fallback and keep all authentication/browser-key storage untouched.
- Verification approach: browser-script tests cover each profile, one-time legacy fallback, foreign preferences, and an authenticated profile change.
- Risks or open questions:
  - Impact: a preference leaks or a login/key is cleared.
  - Early warning / validation: assert storage reads/writes and unchanged auth keys.
  - Mitigation: scope migration to the two named preference keys.
- Canonical components/API contracts touched: layout preference initialization; theme toggle; density control; browser-runtime descriptor.

## Stage 3

- Goal: make worker cache ownership, bootstrap validation, and health reporting profile-aware.
- Dependencies: Stage 1 descriptor.
- Expected changes: derive worker cache names and bootstrap/health identities from the runtime descriptor; refresh stale owned caches and leave foreign caches untouched.
- Verification approach: worker/cache tests cover matching retention, stale refresh, foreign isolation, bootstrap rejection, and diagnostics for all profiles.
- Risks or open questions:
  - Impact: offline data deletion or a misleading healthy status.
  - Early warning / validation: test cache-name classification before cleanup wiring.
  - Mitigation: accept only the active descriptor's exact cache family for ownership operations.
- Canonical components/API contracts touched: service worker; offline bootstrap validation; offline-health diagnostics; cache classification contract.

## Stage 4

- Goal: deliver the active profile's PWA identity through dynamic application routes.
- Dependencies: Stages 1 and 3.
- Expected changes: derive manifest content and worker registration/bootstrap configuration from the active runtime descriptor while retaining one root-scope active worker on a shared origin.
- Verification approach: route/render tests assert each profile's manifest, registration, worker response, and preserved authenticated session after an active-profile change.
- Risks or open questions:
  - Impact: a browser associates a profile with the previous root worker.
  - Early warning / validation: register/refresh sequential profiles at the shared root scope.
  - Mitigation: document and test sequential one-active-profile behavior, not concurrent root workers.
- Canonical components/API contracts touched: manifest route; PWA registration; router/front controller; worker response contract.

## Stage 5

- Goal: make generated physical offline artifacts obey the same contract.
- Dependencies: Stage 4 dynamic delivery contract.
- Expected changes: have the static artifact builder emit profile-matched manifest and worker runtime files and validate physical-file serving paths.
- Verification approach: build and inspect each profile's published artifact root, then request direct manifest and worker files without application routing.
- Risks or open questions:
  - Impact: deployed static hosting uses stale or foreign PWA metadata.
  - Early warning / validation: compare dynamic and physical identity fields per profile.
  - Mitigation: reuse the descriptor-driven artifact renderer rather than raw Zenmemes file copies.
- Canonical components/API contracts touched: `StaticArtifactBuilder`; static artifact layout; manifest/worker artifact renderer.

## Stage 6

- Goal: establish the release regression matrix and record P2 completion.
- Dependencies: Stages 2–5.
- Expected changes: add focused dynamic/static three-profile coverage, including legacy recovery, cache isolation, and authenticated switching; mark only verified P2 browser/offline checklist entries complete.
- Verification approach: run targeted unit, worker, routing, artifact, and browser smoke tests, then the relevant suite; manually confirm one active root worker is expected on a shared origin.
- Risks or open questions:
  - Impact: a test-only dynamic path hides a static-host regression.
  - Early warning / validation: require both delivery modes in every profile row.
  - Mitigation: make the matrix a release gate and retain existing unrelated failure baselines separately.
- Canonical components/API contracts touched: browser/offline regression matrix; P2 checklist; existing test harnesses.
