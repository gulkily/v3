# Multi-Site Refactor P1 — Step 3: Development Plan

> **Feature plan:** [Step 1](./multi_site_refactor_p1_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p1_step2_feature_description.md) · [Step 3](./multi_site_refactor_p1_step3_development_plan.md) · [Step 4](./multi_site_refactor_p1_step4_implementation_summary.md)

## Completion Contract

- Normal entry: the active QDB profile receives a classic QDB URL or a new quote submission.
- End-to-end outcome: QDB selects its own routes, surfaces, and quote-number behavior; a created quote receives a shareable numeric link that resolves to the same quote.
- Required recovery: unknown quote numbers and QDB-only URLs on Zenmemes or Chouse reach the ordinary not-found path without shadowing shared routes.
- Deployment/external verification: exercise the retained classic-route matrix and numeric links on a deployed QDB profile; confirm Zenmemes and Chouse serve their unchanged shared routes.
- Release condition: QDB policy has a named boundary, one quote-number capability owns minting/parse/lookup/display, and no generic QDB conditionals remain in the scoped route, board, chrome, or card surfaces.

## Key Risks

- **High risk: a classic route shadows a shared route.** Early validation: three-profile route matrix. Mitigation: match QDB routes only through its enabled experience and retain ordinary routing precedence.
- **High risk: number creation, lookup, and display diverge.** Early validation: create and resolve quotes at a sequence boundary. Mitigation: one QDB quote-number contract used by every scoped caller.
- **High risk: specialized presentation leaks into generic pages.** Early validation: compare QDB and non-QDB chrome/card output. Mitigation: select named QDB surfaces at the experience boundary, not through generic profile-name branches.

## Stage 1

- Goal: establish the authoritative QDB quote-number contract.
- Dependencies: approved Step 3.
- Expected changes: introduce a QDB quote-number component for thread-ID minting, suffix parsing, numeric lookup, and display-permalink selection; migrate the scoped write, read, and card callers.
- Verification approach: cover first number, sequence boundary, imported-number parsing, missing-number recovery, and non-QDB IDs.
- Risks or open questions:
  - Impact: an incorrect parse or allocation breaks creation or existing short links.
  - Early warning / validation: create and resolve adjacent quote numbers plus an imported quote.
  - Mitigation: preserve existing ID shape and make absent/invalid numbers return no QDB match.
- Canonical components/API contracts touched: new `QdbQuoteNumbers` contract; `LocalWriteService`; `ThreadRepository`; quote-card permalink data.

## Stage 2

- Goal: move classic QDB route matching and dispatch behind the named QDB experience.
- Dependencies: Stage 1 quote-number lookup contract.
- Expected changes: add a QDB experience with conceptual `matches(path, query)` and `dispatch(path, query)` entry points; move QDB route selection and numeric-shortcut handling from the generic application entry point while preserving ordinary route precedence.
- Verification approach: exercise welcome, latest/top and pagination, leetness, add, random, search, query shortcuts, numeric shortcuts, unknown numbers, and their Zenmemes/Chouse results.
- Risks or open questions:
  - Impact: a route becomes unreachable or shadows a shared path.
  - Early warning / validation: route matrix includes both direct paths and legacy query forms.
  - Mitigation: invoke the experience only when the QDB profile enables it and retain generic routing as fallback.
- Canonical components/API contracts touched: `Application` route boundary; `QdbExperience`; `SiteProfileRegistry` enabled-experience metadata.

## Stage 3

- Goal: give QDB its own board policy while reusing shared forum data services.
- Dependencies: Stage 2 route dispatch.
- Expected changes: move QDB latest/top/leetness policy, pagination, reaction state, and board-card selection out of generic controller branches into named QDB experience interfaces.
- Verification approach: render QDB board views with quotes and confirm page size, sort, reaction state, and card selection remain QDB-only.
- Risks or open questions:
  - Impact: QDB output changes or generic board behavior regresses.
  - Early warning / validation: compare existing QDB fixtures and Zenmemes/Chouse board output.
  - Mitigation: reuse the existing shared query/render services and keep QDB policy inputs explicit.
- Canonical components/API contracts touched: `QdbExperience`; board and compose page services; existing QDB page surfaces; shared thread/read-model services.

## Stage 4

- Goal: move QDB's dedicated welcome, random, search, and add surfaces into the experience.
- Dependencies: Stage 2 route dispatch and Stage 3 board policy.
- Expected changes: move QDB welcome, random, search, and compact add orchestration out of generic controller branches into named QDB experience interfaces.
- Verification approach: render every dedicated QDB surface with populated and empty states; verify QDB-only access and the ordinary non-QDB fallback.
- Risks or open questions:
  - Impact: a specialized surface loses its existing query or compose behavior.
  - Early warning / validation: compare QDB page fixtures and submitted-quote outcomes.
  - Mitigation: retain the shared compose and read-model contracts beneath the QDB interfaces.
- Canonical components/API contracts touched: `QdbExperience`; compose and board page services; QDB welcome/random/search/add surfaces.

## Stage 5

- Goal: isolate QDB chrome and card presentation from generic template branches.
- Dependencies: Stages 1, 3, and 4.
- Expected changes: move QDB navigation, footer, and quote-card selection into named QDB presentation interfaces; preserve generic layout mechanics and non-QDB navigation/cards.
- Verification approach: assert QDB chrome and numeric card permalinks, then assert Zenmemes and Chouse retain their current chrome and full-ID links.
- Risks or open questions:
  - Impact: shared pages acquire QDB branding or lose global capabilities.
  - Early warning / validation: render all three profile layouts and one card per profile.
  - Mitigation: select only registered QDB presentation surfaces and leave shared layout data ownership unchanged.
- Canonical components/API contracts touched: `TemplateRenderer`; layout/footer and quote-card surfaces; QDB presentation interfaces.

## Stage 6

- Goal: complete the profile regression contract and release handoff for the QDB vertical slice.
- Dependencies: Stages 1-5.
- Expected changes: add a three-profile QDB experience matrix covering routes, surfaces, quote creation, numeric links, recovery, and profile isolation; document deployed-route verification in the Step 4 summary.
- Verification approach: run focused QDB, write, board, card, and application tests; run the complete suite; record pre-existing failures separately; perform the deployment route matrix.
- Risks or open questions:
  - Impact: coverage misses a legacy URL or environment-specific route behavior.
  - Early warning / validation: enumerate every classic URL before test execution and compare deployed responses.
  - Mitigation: block release on a passing matrix or an explicitly documented, pre-existing failure.
- Canonical components/API contracts touched: QDB experience regression coverage; application route coverage; quote-number and presentation tests; Step 4 implementation summary.
