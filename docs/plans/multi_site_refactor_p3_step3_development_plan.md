# Multi-Site Refactor P3 — Step 3: Development Plan

> **Feature plan:** [Step 1](./multi_site_refactor_p3_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p3_step2_feature_description.md) · [Step 3](./multi_site_refactor_p3_step3_development_plan.md) · [Step 4](./multi_site_refactor_p3_step4_implementation_summary.md)

## Completion Contract

- Normal entry: existing configuration selects a registered profile.
- End-to-end outcome: one descriptor-derived contract verifies registered profiles and a fourth fixture for routes, presentation, runtime identity, static output, and shared state.
- Required recovery: missing or unknown selection retains the default fallback; invalid fixture data fails registry validation.
- Deployment/external verification: none; this is internal test maintenance. Local dynamic and physical-static artifact checks are the delivery evidence.
- Release condition: targeted tests and the relevant full suite pass or record pre-existing failures; then all three P3 checklist entries are checked with matching evidence.

## Key Risks

- Impact: descriptor data may not express a current expectation. Early validation: inventory literals before migration. Mitigation: add only reusable contract data; replan if product behavior is needed.
- Impact: runtime/static fixtures can leak profile state. Early validation: run each profile in isolated temporary roots. Mitigation: retain profile-scoped cleanup and shared-state assertions.
- Impact: a fourth profile may need a production-only behavior. Early validation: run its fixture through the shared contract first. Mitigation: use validated slots/experiences or return to Step 2.

## Stage 1

- Goal: establish a test-only descriptor-derived expectation source.
- Dependencies: approved Steps 1–2.
- Expected changes: inventory fixed profile literals; add/reuse shared test expectations from profile, slot, experience, runtime, and path contracts.
- Verification approach: targeted registry, theme, and static-path tests pass with no existing-site expectation branch.
- Risks or open questions: Impact: a needed expectation is absent. Early warning / validation: compare every migrated assertion to a canonical source. Mitigation: stop before dependent matrix work and add only reusable contract data.
- Canonical components/API contracts touched: `SiteProfileRegistry`, `PresentationSlotRegistry`, `BrowserRuntimeProfile`, `PresentationPathResolver`.

## Stage 2

- Goal: make route and presentation checks consume the shared contract for all registered profiles.
- Dependencies: Stage 1 expectation source.
- Expected changes: replace fixed profile matrices in experience, theme, navigation, card, compose, about, and chrome regression coverage.
- Verification approach: targeted presentation, theme, and experience-route tests pass for every registered profile.
- Risks or open questions: Impact: generic assertions hide a specialized route. Early warning / validation: assert both enabled and rejected experience paths. Mitigation: derive availability from enabled experiences, not profile identity.
- Canonical components/API contracts touched: experience routing, `TemplateRenderer`, registered presentation slots, theme contract.

## Stage 3

- Goal: generalize browser and offline/PWA identity coverage.
- Dependencies: Stage 1 runtime expectations; completed P2 browser/offline contract.
- Expected changes: replace fixed preference, cache, manifest, worker, and cache-cleanup expectations with runtime-profile values.
- Verification approach: targeted browser-runtime, worker, and dynamic manifest/worker tests pass across all registered profiles.
- Risks or open questions: Impact: cache isolation is falsely green. Early warning / validation: retain matching, stale, and foreign-cache cases. Mitigation: isolate runtime fixture state per profile.
- Canonical components/API contracts touched: `BrowserRuntimeProfile`, runtime asset delivery, offline worker lifecycle.

## Stage 4

- Goal: verify profile-derived static output and preserved shared instance state.
- Dependencies: Stages 2–3.
- Expected changes: make static-output and profile-switch/session expectations descriptor-derived; retain physical artifact checks.
- Verification approach: targeted static-artifact and shared-session tests pass for every registered profile.
- Risks or open questions: Impact: an artifact or session crosses profiles. Early warning / validation: use distinct temporary roots and one public session. Mitigation: assert isolated roots/runtime assets and unchanged authenticated identity.
- Canonical components/API contracts touched: `PresentationPathResolver`, static-artifact publication, public-session handling.

## Stage 5

- Goal: prove extension with a fourth-site fixture and required recovery paths.
- Dependencies: Stages 1–4.
- Expected changes: add a valid fourth descriptor fixture to the same test contract; cover invalid fixture validation and default fallback. Add a narrow test-only profile source only if the shared contract cannot otherwise select the fixture.
- Verification approach: fourth fixture passes without a fixture-specific assertion branch; invalid and unknown selections retain their defined outcomes.
- Risks or open questions: Impact: fixture requires new product behavior. Early warning / validation: attempt the contract unchanged first. Mitigation: return to Step 2 rather than adding a site-specific workaround.
- Canonical components/API contracts touched: `SiteProfileRegistry` validation/default selection, shared test contract.

## Stage 6

- Goal: record evidence and close the approved P3 scope.
- Dependencies: Stages 1–5 and passing targeted verification.
- Expected changes: update only the three P3 checklist entries with completed status; preserve P2 evidence.
- Verification approach: run the targeted matrix, relevant full suite, `git diff --check`, and record any pre-existing failures.
- Risks or open questions: Impact: checklist overstates coverage. Early warning / validation: map each entry to passing tests before editing it. Mitigation: leave any unverified entry open.
- Canonical components/API contracts touched: `docs/plans/multi_site_refactor_checklist.md`; no production contract change.
