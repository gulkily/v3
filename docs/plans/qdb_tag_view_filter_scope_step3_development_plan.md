# QDB Tag View Filter Scope — Step 3: Development Plan

> **Feature plan:** [Step 1](./qdb_tag_view_filter_scope_step1_solution_assessment.md) · [Step 2](./qdb_tag_view_filter_scope_step2_feature_description.md) · [Step 3](./qdb_tag_view_filter_scope_step3_development_plan.md) · [Step 4](./qdb_tag_view_filter_scope_step4_implementation_summary.md)

## Completion Contract

- Normal entry: on QDB, a visitor opens `/tags/general` for a mixed quote/non-quote tag.
- End-to-end outcome: the tag page contains both roots; QDB latest, top, leetness, random, search, and RSS contain only the quote root.
- Required recovery: an unknown tag, direct thread permalink, and non-QDB tag behavior retain their existing outcomes.
- Deployment/external verification: a QDB static build emits and serves the complete `/tags/general` artifact through the existing static route path.
- Release condition: the targeted QDB boundary and static-route tests pass, with no new failures in the relevant suite.

## Key Risks

- **High risk:** shared tag grouping receives quote filtering. Impact: QDB readers lose valid tagged content. Early validation: mixed fixture on `/tags/general`. Mitigation: assert both roots before validating collections.
- A collection bypasses the policy. Impact: a non-quote is presented as a quote. Early validation: one mixed-fixture matrix across each QDB collection route and feed. Mitigation: assert only the canonical quote root on every surface.
- Static output diverges from dynamic output. Impact: deployed tag links are incomplete. Early validation: generated artifact plus static-route resolution. Mitigation: cover both in the same QDB fixture.

## Stage 1

- Goal: lock the dynamic boundary between all-content tag pages and quote-only QDB collections.
- Dependencies: approved Step 2; existing mixed root/quote test fixture and `QdbBoardPolicy` eligibility contract.
- Expected changes: add focused QDB-profile regression coverage with a quote and non-quote sharing `general`; assert both at `/tags/general` and only the quote across latest, top, leetness, random, search, and RSS.
- Verification approach: run the focused QDB rendering and policy tests; confirm the mixed tag page and each quote collection produce their specified root set.
- Risks or open questions:
  - Impact: assertions could test a generic profile rather than QDB.
  - Early warning / validation: set and clear the QDB profile in the test around every rendered route.
  - Mitigation: keep the profile setup adjacent to the route matrix.
- Canonical components/API contracts touched: `TagsPageController`, `ThreadRepository`, `TagGrouping`, `QdbBoardPolicy::eligibleQuotes()`, `BoardPageController`, and `QdbExperience` (regression use only).

## Stage 2

- Goal: prove the QDB deployment artifact retains the same complete tag result.
- Dependencies: Stage 1 dynamic boundary coverage.
- Expected changes: extend existing QDB static-artifact coverage to assert `/tags/general` is emitted with both mixed-fixture roots and is selected by the existing static tag-route resolution.
- Verification approach: build QDB artifacts, inspect the tag artifact content, resolve `/tags/general` through the front controller, and rerun the targeted static and QDB tests.
- Risks or open questions:
  - Impact: checking only artifact existence could miss filtered content.
  - Early warning / validation: assert both root identifiers in the generated page.
  - Mitigation: pair content assertions with the static-route assertion.
- Canonical components/API contracts touched: `StaticArtifactBuilder`, `FrontController` tag resolution, `TagsPageController`, and the existing QDB test fixture.
