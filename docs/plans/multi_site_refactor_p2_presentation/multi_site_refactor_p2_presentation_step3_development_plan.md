# Multi-Site Refactor P2 Presentation — Step 3: Development Plan

> **Feature plan:** [Step 1](./multi_site_refactor_p2_presentation_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p2_presentation_step2_feature_description.md) · [Step 3](./multi_site_refactor_p2_presentation_step3_development_plan.md) · [Step 4](./multi_site_refactor_p2_presentation_step4_implementation_summary.md)

## Completion Contract

- Normal entry: a visitor loads an ordinary page or opens the theme menu on Zenmemes, Chouse, or QDB.
- End-to-end outcome: the active profile selects registered navigation, board-card, compose, about/editorial, and branded-stylesheet slots plus its permitted theme menu.
- Required recovery: unset, invalid, or unavailable selections resolve to registered shared/default choices without arbitrary file paths or asset stacks.
- Deployment/external verification: render all three profiles through the deployed entry point and verify branded theme availability and fallback pages.
- Release condition: P2 presentation checklist items are complete; P1 QDB behavior remains specialized; no arbitrary presentation path is accepted.

## Key Risks

- **High risk: a missing slot breaks a public page.** Early validation: render each slot with absent/invalid selections. Mitigation: validate selections and provide shared fallbacks.
- **High risk: saved unavailable themes produce an unusable page.** Early validation: load each foreign theme preference. Mitigation: reset to the profile default and expose only permitted menu choices.
- **High risk: P2 absorbs QDB policy.** Early validation: review QDB changes against its P1 experience. Mitigation: P2 may select presentation assets only.

## Stage 1

- Goal: establish the validated registered presentation-slot contract.
- Dependencies: approved Step 3.
- Expected changes: add named slot selections and shared fallbacks for navigation, board card, compose, about/editorial, and branded stylesheet; reject unregistered selections.
- Verification approach: unit-test every profile, fallback, duplicate/unknown selection, and path-safety boundary.
- Risks or open questions:
  - Impact: malformed profile metadata prevents rendering.
  - Early warning / validation: validate the complete three-profile registry at load time.
  - Mitigation: deterministic fallback slots and a closed catalog.
- Canonical components/API contracts touched: `SiteProfileRegistry`; new presentation-slot registry/resolver.

## Stage 2

- Goal: make shared layout chrome select registered navigation and stylesheet slots.
- Dependencies: Stage 1 slot resolver.
- Expected changes: migrate navigation and branded stylesheet choice from renderer conditionals to registered slots while retaining shared layout fallback.
- Verification approach: render chrome for all profiles and assert the selected nav/style slot plus fallback.
- Risks or open questions:
  - Impact: a profile receives foreign chrome or missing styles.
  - Early warning / validation: compare profile layout assets and navigation labels.
  - Mitigation: permit only named registered assets.
- Canonical components/API contracts touched: `TemplateRenderer`; layout template; navigation and stylesheet slots.

## Stage 3

- Goal: make board-card and compose presentation profile-selected.
- Dependencies: Stages 1-2.
- Expected changes: route ordinary board-card and compose-surface selection through registered slots; retain QDB's existing experience-selected presentation as its specialized consumer.
- Verification approach: render boards and compose pages for all profiles; verify QDB cards/compose remain unchanged and generic fallbacks render.
- Risks or open questions:
  - Impact: card/compose forms lose required data or signing behavior.
  - Early warning / validation: submit/render smoke coverage for each selected surface.
  - Mitigation: reuse existing canonical card and compose contracts.
- Canonical components/API contracts touched: board controller/template; compose rendering; QDB presentation interfaces.

## Stage 4

- Goal: move editorial and branded page content into bounded selections.
- Dependencies: Stage 1 content slots.
- Expected changes: migrate Zenmemes/Boston editorial content, Chouse about content, platform-document branding, and the busy message into profile-owned content data or registered partials.
- Verification approach: render affected normal/error/document pages for each profile and verify shared fallback copy.
- Risks or open questions:
  - Impact: error/document flows expose the wrong brand.
  - Early warning / validation: profile matrix includes normal and recovery pages.
  - Mitigation: use the same validated slot resolver in every scoped renderer.
- Canonical components/API contracts touched: about, platform-document, and front-controller message surfaces; editorial/content slots.

## Stage 5

- Goal: make branded theme availability profile-owned with safe preference recovery.
- Dependencies: Stages 1-2.
- Expected changes: filter theme-menu choices by profile permissions; recover an unavailable saved theme to the profile default without changing unrelated browser/offline keys.
- Verification approach: test each permitted menu, foreign saved Chouse/QDB theme, default recovery, and unchanged non-theme preferences.
- Risks or open questions:
  - Impact: a visitor loses access to a usable theme.
  - Early warning / validation: load pages with every stored-theme/profile combination.
  - Mitigation: one validated permitted-theme contract with default fallback.
- Canonical components/API contracts touched: theme registry/menu; theme initialization and toggle assets; profile descriptor theme fields.

## Stage 6

- Goal: finish the presentation regression contract and checklist handoff.
- Dependencies: Stages 1-5.
- Expected changes: add a three-profile slot/theme/fallback matrix; update the P2 presentation checklist and Step 4 summary.
- Verification approach: run focused presentation/theme/application tests and the full suite; record pre-existing failures separately; complete deployed render checks.
- Risks or open questions:
  - Impact: an untested slot or deployed artifact diverges.
  - Early warning / validation: enumerate every slot and profile before release.
  - Mitigation: block release on a passing matrix or documented pre-existing failures only.
- Canonical components/API contracts touched: presentation/theme regression coverage; checklist; Step 4 implementation summary.
