# QDB Site News — Step 4: Implementation Summary

> **Feature plan:** [Step 1](./qdb_site_news_step1_solution_assessment.md) · [Step 2](./qdb_site_news_step2_feature_description.md) · [Step 3](./qdb_site_news_step3_development_plan.md) · [Step 4](./qdb_site_news_step4_implementation_summary.md)

## Stage 1 - Canonical news selection

- Changes:
  - Added QDB policy selection for visible `news`-tagged threads in descending authored-date order.
  - Supplied the Welcome template with the first three news entries and a flag for additional items, while retaining quote count and prior recent-thread data until the presentation stage.
  - Added policy coverage for a non-quote news entry and date ordering.
- Verification:
  - `php tests/run.php QdbBoardPolicyTest` — 5 passed, 0 failed.
  - `git diff --check` — passed.
- Notes:
  - No database, route, API, or generic board behavior changed.

## Stage 2 - Site News presentation

- Changes:
  - Replaced the QDB Welcome “Recent activity” list with Site News title/date links, an empty state, and a conditional `/tags/news` continuation.
  - Added QDB Welcome coverage for a non-quote four-item news collection, three-item cap, authored-date order, title fallback, and the all-news link.
  - Updated the quote-collection regression to exclude Welcome, which is now a news surface rather than a quote collection.
- Verification:
  - `php tests/run.php QuoteCardDisplayNumberTest` — 18 passed, 0 failed.
  - `git diff --check` — passed.
- Notes:
  - No new route or presentation-specific title/date formatter was introduced.
