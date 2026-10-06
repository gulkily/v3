# QDB Quote Listing Filter — Step 3: Development Plan

> **Feature plan:** [Step 1](./qdb_quote_listing_filter_step1_solution_assessment.md) · [Step 2](./qdb_quote_listing_filter_step2_feature_description.md) · [Step 3](./qdb_quote_listing_filter_step3_development_plan.md) · [Step 4](./qdb_quote_listing_filter_step4_implementation_summary.md)

## Completion Contract

- Normal entry: QDB readers open welcome, latest, top, leetness, random, search, or board RSS.
- End-to-end outcome: every collection, count, sort, and pagination result contains only roots recognized by the existing QDB quote-ID contract.
- Required recovery: full historic legacy-thread permalinks remain resolvable; unknown numeric quotes and absent threads keep their current rejection.
- Deployment/external verification: none; dynamic application and RSS verification are sufficient for this QDB-only behavior change.
- Release condition: mixed-fixture QDB collection and recovery tests pass, the relevant suite has no new failures, and each stage is recorded in the Step 4 summary.

## Key Risks

- Impact: a QDB collection path or RSS bypasses the filter. Early validation: enumerate each reader-facing route. Mitigation: route matrix with a quote and legacy root before completion.
- Impact: filtering after sorting or pagination produces empty pages and wrong counts. Early validation: mixed fixture across a page boundary. Mitigation: apply eligibility before every collection operation.
- Impact: direct recovery changes accidentally. Early validation: historic full permalink and unknown numeric-route tests. Mitigation: leave direct-route resolution outside the collection filter.

## Stage 1

- Goal: establish one canonical QDB collection-eligibility policy.
- Dependencies: approved Steps 1–2.
- Expected changes: add a policy operation conceptually shaped as `eligibleQuotes(array $threads): array`, using `QdbQuoteNumbers` rather than a new parser; cover quote, legacy, and malformed IDs.
- Verification approach: targeted quote-number and board-policy tests prove only recognized quote roots are retained.
- Risks or open questions: Impact: parser drift. Early warning / validation: malformed suffix fixture. Mitigation: delegate eligibility exclusively to `QdbQuoteNumbers`.
- Canonical components/API contracts touched: `QdbQuoteNumbers`, `QdbBoardPolicy`.

## Stage 2

- Goal: apply eligibility before QDB board counts, sort order, and pagination.
- Dependencies: Stage 1 policy.
- Expected changes: pass fetched roots through the QDB policy before latest, top, and leetness operations; derive the quote count from the same eligible set.
- Verification approach: mixed roots across a page boundary produce quote-only cards, accurate count, and non-sparse pagination.
- Risks or open questions: Impact: generic boards change. Early warning / validation: run generic board coverage with the same fixture. Mitigation: invoke the policy only for the selected QDB experience.
- Canonical components/API contracts touched: `BoardPageController`, `QdbBoardPolicy`, board-card presentation.

## Stage 3

- Goal: apply the same policy to remaining QDB collections and RSS.
- Dependencies: Stages 1–2.
- Expected changes: source welcome, random, and search from eligible roots; extend the existing RSS path, conceptually `rss(?QdbBoardPolicy $policy)`, so QDB feed items use the selected policy.
- Verification approach: welcome, random, search, and `/?format=rss` exclude the legacy root while retaining the quote.
- Risks or open questions: Impact: RSS remains a generic bypass. Early warning / validation: inspect feed item IDs in the mixed fixture. Mitigation: wire the selected QDB policy at the existing application RSS dispatch point.
- Canonical components/API contracts touched: `QdbExperience`, `BoardPageController` RSS, application route dispatch, `QdbBoardPolicy`.

## Stage 4

- Goal: prove recovery, profile isolation, and release readiness.
- Dependencies: Stages 1–3.
- Expected changes: add end-to-end coverage for direct historic links, missing numeric quotes, and the unchanged generic listing; update the Step 4 summary.
- Verification approach: run targeted QDB policy, routing, quote-card, and RSS tests; run the relevant suite and `git diff --check`.
- Risks or open questions: Impact: recovery or another profile regresses. Early warning / validation: assert both the legacy direct link and generic board contain the legacy root. Mitigation: leave any failing scope uncompleted and return to planning if a broader behavior change is needed.
- Canonical components/API contracts touched: direct thread routing, numeric quote routing, generic board flow, Step 4 summary.
