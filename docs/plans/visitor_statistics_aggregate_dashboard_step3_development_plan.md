> **Feature plan:** [Step 1](./visitor_statistics_aggregate_dashboard_step1_solution_assessment.md) · [Step 2](./visitor_statistics_aggregate_dashboard_step2_feature_description.md) · [Step 3](./visitor_statistics_aggregate_dashboard_step3_development_plan.md) · [Step 4](./visitor_statistics_aggregate_dashboard_step4_implementation_summary.md)

# Step 3: Development Plan — Aggregate Visitor Statistics Dashboard

## Completion Contract

- Normal entry: a root-approved operator opens Visitor Statistics from Tools and selects 24 hours, 7 days, 30 days, or 90 days.
- End-to-end outcome: the protected page shows truthful aggregate request totals, an accessible time trend, and anonymous/server-authenticated request counts.
- Required recovery: initializing, partial-history, and unavailable states are explicit; invalid periods fall back safely; unauthorized viewers remain forbidden.
- Deployment/external verification: private state remains outside published artifacts, 90-day retention is documented, and traffic served outside PHP remains outside coverage.
- Release condition: focused store, observer, page, authorization, and static/dynamic-path coverage passes, along with the full relevant test suite and static-artifact checks.

## Key Risks

- **High risk: Legacy daily data cannot become hourly history.** Impact: invented or misleading trends. Early validation: open an existing daily-only state file. Mitigation: begin a clearly labeled hourly collection epoch; never synthesize hour buckets.
- **High risk: Authentication split misclassifies identity hints.** Impact: reported signed-in traffic is untrustworthy. Early validation: exercise session, hint-only, and no-cookie requests. Mitigation: classify only a verified server-side authenticated identity; static artifact delivery is anonymous because it is selected only when no cookies exist.
- **High risk: Aggregate state becomes identifiable.** Impact: privacy-contract breach. Early validation: inspect schema after both traffic classes. Mitigation: allow only UTC buckets, counters, and mergeable cardinality sketches; reject raw keys, sessions, identities, paths, headers, and referrers.

## Stage 1

- Goal: establish a bounded hourly aggregate epoch that can represent every supported rolling period.
- Dependencies: approved Step 2 metric and privacy contract.
- Expected changes: extend `VisitorStatisticsStore` with hourly aggregate buckets, a 90-day expiry policy, collection-start/partial-history metadata, and a new summary contract; retain no raw fields and do not convert legacy daily buckets into hourly data.
- Verification approach: store tests cover UTC boundaries, 24-hour/7-day/30-day/90-day windows, expiry, legacy-state handling, and schema-field privacy inspection.
- Risks or open questions:
  - Impact: bitmap estimates can be mistaken for exact counts.
  - Early warning / validation: merge repeat-client and repeat-user buckets in tests.
  - Mitigation: retain explicit estimated-count labels in the summary contract.
- Canonical components/API contracts touched: `VisitorStatisticsStore::recordVisit()` and summary payload; private visitor-statistics SQLite state.

## Stage 2

- Goal: record the anonymous/server-authenticated request split without changing eligibility or fail-open behavior.
- Dependencies: Stage 1 hourly aggregate contract.
- Expected changes: extend observation input to classify a request from the existing verified identity value; increment aggregate anonymous or server-authenticated request counters while preserving aggregate client and authenticated-user estimates.
- Verification approach: observer tests cover eligible/excluded traffic, verified-session classification, hint-only classification, static no-cookie handling, and recorder failure.
- Risks or open questions:
  - Impact: a client changing state can appear in both distinct-client estimates.
  - Early warning / validation: record one client before and after authentication in one range.
  - Mitigation: expose the split as requests, not additive unique clients.
- Canonical components/API contracts touched: `VisitorStatisticsObserver`, Application’s authenticated-viewer lookup, and FrontController static-artifact observation.

## Stage 3

- Goal: expose validated period-specific summaries through the existing protected route.
- Dependencies: Stages 1–2.
- Expected changes: pass the existing parsed query into `VisitorStatisticsController`; add a supported-period selection contract and summary metadata for totals, buckets, coverage, and recovery status.
- Verification approach: controller/page tests cover all supported periods, invalid selection fallback, root-session access, hint-only denial, and partial/unavailable responses.
- Risks or open questions:
  - Impact: route query parsing can change unrelated tools behavior.
  - Early warning / validation: exercise the canonical route with and without its query.
  - Mitigation: confine period parsing and defaults to Visitor Statistics.
- Canonical components/API contracts touched: `Application::handle()`, `VisitorStatisticsController`, and the protected Viewer Statistics summary closure.

## Stage 4

- Goal: render an accessible, dependency-free operator dashboard.
- Dependencies: Stage 3 summary payload.
- Expected changes: extend `visitor_statistics.php` with period controls, totals, anonymous/server-authenticated request presentation, server-rendered inline trend graphic, fallback table, and clear coverage/privacy/recovery copy; register a scoped page stylesheet through `TemplateRenderer`.
- Verification approach: rendered-page assertions cover labels, selected period, table equivalents, SVG text alternatives, thousands formatting, and light/dark theme-safe CSS hooks.
- Risks or open questions:
  - Impact: charts can hide sparse or incomplete data.
  - Early warning / validation: render empty, partial, and 90-day samples.
  - Mitigation: keep the status language and table primary for accessibility; add no chart framework or CDN.
- Canonical components/API contracts touched: `templates/pages/visitor_statistics.php`, `TemplateRenderer::PAGE_STYLESHEET_PATHS`, and a page-scoped asset under `public/assets/`.

## Stage 5

- Goal: verify end-to-end privacy, authorization, and static-delivery behavior.
- Dependencies: Stages 1–4.
- Expected changes: extend focused visitor-statistics tests and the relevant application/static smoke coverage for the new aggregate contract and recovery states.
- Verification approach: run focused PHP tests, the complete test suite, PHP lint, `git diff --check`, and static-artifact validation.
- Risks or open questions:
  - Impact: a seemingly harmless response field can expose retained data.
  - Early warning / validation: assert persisted columns and rendered output lack prohibited fields.
  - Mitigation: retain negative privacy assertions beside feature tests.
- Canonical components/API contracts touched: `VisitorStatisticsStoreTest`, `VisitorStatisticsObserverTest`, `VisitorStatisticsPageTest`, `LocalAppSmokeTest`, and static-artifact checks.

## Stage 6

- Goal: document the changed operational retention and coverage boundary.
- Dependencies: Stages 1–5.
- Expected changes: update the production deployment runbook and Step 4 implementation summary with the aggregate-only hourly lifecycle, collection-epoch limitation, PHP/static coverage boundary, and verification evidence.
- Verification approach: review documentation against the implemented fields and route behavior; verify links, declared retention, and repository status.
- Risks or open questions:
  - Impact: operators make decisions based on stale daily-retention documentation.
  - Early warning / validation: compare runbook claims with store retention tests.
  - Mitigation: make the runbook describe only tested behavior and private-state placement.
- Canonical components/API contracts touched: `docs/runbooks/production_deploy.md` and `visitor_statistics_aggregate_dashboard_step4_implementation_summary.md`.
