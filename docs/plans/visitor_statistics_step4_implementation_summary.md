> **Feature plan:** [Step 1](./visitor_statistics_step1_solution_assessment.md) · [Step 2](./visitor_statistics_step2_feature_description.md) · [Step 3](./visitor_statistics_step3_development_plan.md) · [Step 4](./visitor_statistics_step4_implementation_summary.md)

# Step 4: Implementation Summary — Visitor Statistics

## Stage 1 - Private aggregate statistics state

- Changes:
  - Added a configurable private SQLite path for visitor-statistics state.
  - Added daily visit counters and fixed-size, mergeable cardinality bitmaps for clients and authenticated users; the schema has no raw client, identity, route, network, or header fields.
  - Added 32-day expiry and 1/7/30-day summary support, including an initializing state.
- Verification:
  - `php tests/run.php VisitorStatisticsDatabaseConfigTest VisitorStatisticsStoreTest` — 4 passed.
  - `php -l src/ForumRewrite/Statistics/VisitorStatisticsDatabaseConfig.php` and `php -l src/ForumRewrite/Statistics/VisitorStatisticsStore.php` — no syntax errors.
  - `git diff --check` — passed.
- Notes:
  - Distinct counts are cardinality estimates; the later page will label client counts accordingly.
  - Runtime, UI, deployment, and migration verification are not applicable until later stages; this stage creates private runtime state only.

## Stage 2 - Safe page-request observation

- Changes:
  - Added a shared, fail-open observer for eligible GET page requests, with API, asset, non-GET, statistics-page, and recognizable-automation exclusion.
  - Wired the observer into the mutually exclusive static `FrontController` and dynamic `Application` page paths.
  - Added the private state-path override to the existing private-configuration loader.
- Verification:
  - `php tests/run.php VisitorStatisticsObserverTest VisitorStatisticsStoreTest VisitorStatisticsDatabaseConfigTest` — 6 passed.
  - `php -l src/ForumRewrite/Statistics/VisitorStatisticsObserver.php` and `php -l src/ForumRewrite/Support/PrivateConfig.php` — no syntax errors.
  - `git diff --check` — passed.
- Notes:
  - The observer derives its aggregate contribution in memory from request metadata; it persists only Stage 1's counter/bitmap fields.
  - UI, deployment, and release checks remain for later stages; recorder-failure behavior is covered directly here.
