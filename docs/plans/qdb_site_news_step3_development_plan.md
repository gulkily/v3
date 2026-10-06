# QDB Site News — Step 3: Development Plan

> **Feature plan:** [Step 1](./qdb_site_news_step1_solution_assessment.md) · [Step 2](./qdb_site_news_step2_feature_description.md) · [Step 3](./qdb_site_news_step3_development_plan.md) · [Step 4](./qdb_site_news_step4_implementation_summary.md)

## Completion Contract

- Normal entry: a visitor opens the QDB Welcome page.
- End-to-end outcome: Site News shows at most three newest visible #news roots, regardless of quote ID, with title/date links and an `/tags/news` continuation when older items exist.
- Required recovery: no #news roots gives a clear empty state; regular QDB quote collections and the established tag route keep their present behavior.
- Deployment/external verification: no external service or deployment-configuration change; validate the dynamic page and applicable static artifact before release.
- Release condition: focused regression coverage and the relevant suite pass, static output contains valid welcome/tag destinations where applicable, and the Step 4 summary records evidence.

## Key Risks

- **High risk:** stale quote-ID or last-activity selection could hide or reorder news. Early validation: mixed data containing a non-quote #news root and later reply activity. Mitigation: make one QDB news-selection rule based on root tag and authored date before presentation.
- **High risk:** a fourth item or missing continuation makes older news unreachable. Early validation: four #news roots. Mitigation: assert a three-item cap and `/tags/news` link together.
- Titleless news could render unclear navigation text. Early validation: titleless #news fixture. Mitigation: reuse the established thread-title fallback rather than introduce panel-specific text rules.

## Stage 1

- Goal: define the canonical QDB Welcome news collection.
- Dependencies: approved Step 2; existing visible thread rows and hydrated board tags.
- Expected changes: extend `QdbBoardPolicy` with a #news top-level selection ordered by root authored date; have `QdbExperience` supply the selected collection and whether additional items exist, without changing quote eligibility or schema.
- Verification approach: focused policy coverage proves #news-only inclusion, non-quote eligibility, authored-date order despite reply activity, and the three-item boundary data.
- Risks or open questions:
  - Impact: generic board or quote behavior could drift if its collection is reused incorrectly.
  - Early warning / validation: existing quote-policy tests plus a non-news/quote mixed fixture.
  - Mitigation: confine the new selector to the QDB Welcome path and retain existing quote methods unchanged.
- Canonical components/API contracts touched: `QdbBoardPolicy`, `QdbExperience`, `ThreadRepository` row contract.

## Stage 2

- Goal: present the selected collection as an accessible Site News panel.
- Dependencies: Stage 1 selected items and additional-item state.
- Expected changes: update `qdb_welcome.php` to use the Site News heading, linked existing thread titles, authored dates, a no-news state, and a conditional `/tags/news` continuation; preserve the surrounding Welcome layout.
- Verification approach: QDB Welcome integration coverage asserts exactly three newest rendered title/date links, exclusion of non-news/older fourth items, title fallback, empty state, and continuation presence only when applicable.
- Risks or open questions:
  - Impact: presentation can show raw IDs, body previews, or an inaccessible date/link.
  - Early warning / validation: HTML assertions for heading, thread destinations, date markup, and absence of old Recent activity content.
  - Mitigation: reuse the established thread-title and timestamp presentation conventions.
- Canonical components/API contracts touched: `qdb_welcome.php`, page-template helpers, QDB thread permalink contract.

## Stage 3

- Goal: validate the released QDB path and retained all-news destination.
- Dependencies: Stages 1–2 complete.
- Expected changes: add the focused QDB Welcome regression to the test runner if needed; no new route, API, schema, or static-route rule.
- Verification approach: run the focused policy/page tests and relevant QDB suite, then build/check static artifacts under the QDB profile to confirm Welcome and `/tags/news` resolve with expected links.
- Risks or open questions:
  - Impact: dynamic behavior passes while the deployed static page lacks its continuation destination.
  - Early warning / validation: inspect generated Welcome and tag artifacts after a four-news fixture build.
  - Mitigation: reuse the existing tag route and static artifact enumeration; do not add a parallel route.
- Canonical components/API contracts touched: `TagsPageController`, `/tags/news` route contract, `StaticArtifactBuilder`, test runner.
