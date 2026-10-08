> **Feature plan:** [Step 1](./visitor_statistics_step1_solution_assessment.md) · [Step 2](./visitor_statistics_step2_feature_description.md) · [Step 3](./visitor_statistics_step3_development_plan.md) · [Step 4](./visitor_statistics_step4_implementation_summary.md)

# Step 3: Development Plan — Visitor Statistics

## Completion Contract

- **Normal entry:** A server-authenticated, root-approved operator selects Visitor Statistics from Tools.
- **End-to-end outcome:** The page presents 24-hour, 7-day, and 30-day eligible visits, estimated distinct clients, and distinct authenticated users, with coverage and privacy definitions.
- **Required recovery:** Unavailable or newly initialized private statistics state renders an explicit unavailable/initializing state; collection failures never prevent a visitor response.
- **Deployment/external verification:** Confirm the private statistics-state path is writable and excluded from public artifacts, then make eligible static and dynamic page requests against a deployed-like host.
- **Release condition:** Automated aggregate-window, expiry, route-classification, access-control, privacy, empty-state, and Tools-navigation coverage passes.

## Key Risks

- **High risk: Privacy/data retention.** Impact: raw identifiers or visit histories could turn basic counts into tracking. Early validation: inspect persisted state after representative requests. Mitigation: store only daily counters and mergeable cardinality aggregates; enforce bounded expiry.
- **High risk: Incorrect coverage.** Impact: caches, static delivery, and automation can make counts misleading. Early validation: exercise static, dynamic, asset, API, bot, and authenticated requests. Mitigation: instrument both eligible page-serving paths, exclude non-page traffic, and state coverage on the page.
- **High risk: Operator-only data exposure.** Impact: viewers could learn operational use. Early validation: test anonymous, identity-hint-only, approved non-root, and server-authenticated root sessions. Mitigation: make the render route enforce server-authenticated root approval.
- **High risk: Runtime-state failure.** Impact: a lock or storage fault could affect normal browsing. Early validation: simulate unavailable state. Mitigation: best-effort recording with no visitor-facing failure; render a recoverable operator status.

## Stage 1

- Goal: Establish privacy-bounded daily statistics and window summaries.
- Dependencies: none.
- Expected changes: Add a private, configurable visitor-statistics state store; define `recordVisit(VisitObservation): void` and `summary(array $windows): VisitorStatisticsSummary`; retain daily visit counters plus mergeable distinct-client and distinct-authenticated-user aggregates only; expire data beyond the largest display window plus recovery margin.
- Verification approach: Unit-test daily recording, window merging, expiry, empty/initializing summaries, and proof that persisted state has no raw client, identity, route, IP, user-agent, or referrer fields.
- Risks or open questions:
  - Impact: distinct-count precision could be mistaken for exact identity tracking.
  - Early warning / validation: compare repeat and multiple-client fixtures against documented estimate behavior.
  - Mitigation: label the client count as estimated and keep the aggregate representation non-inspectable.
- Canonical components/API contracts touched: new private statistics configuration/store contract; separate from the read model and public/static artifacts.

## Stage 2

- Goal: Record every eligible server-observed page visit without tracking assets, APIs, or visitors individually.
- Dependencies: Stage 1.
- Expected changes: Add one shared request classifier/observer used by `FrontController` for cookie-free static pages and `Application` for dynamic pages; observe GET page requests only, transiently classify recognizable automation, derive aggregate client/authenticated-user contributions, and fail open when recording cannot proceed.
- Verification approach: Cover eligible static and dynamic pages, repeat client/user visits, API and asset exclusion, recognizable bot exclusion, non-GET exclusion, and a forced recorder failure that still returns the page.
- Risks or open questions:
  - Impact: static and dynamic paths may double-count or leave a coverage gap.
  - Early warning / validation: issue the same route under both serving modes and assert one observation per response path.
  - Mitigation: centralize eligibility and document that CDN/edge responses outside PHP are excluded.
- Canonical components/API contracts touched: `Host\FrontController`, `Application::handle()`, and the shared visitor-statistics observer; no public analytics API.

## Stage 3

- Goal: Deliver the protected in-product statistics outcome through Tools.
- Dependencies: Stages 1–2.
- Expected changes: Add the Visitor Statistics destination to `ToolsPageSupport`; add a controller/template render path that consumes `VisitorStatisticsSummary`; require a server-authenticated root-approved viewer before reading any summary; render metric definitions, coverage/privacy notice, and unavailable/initializing state.
- Verification approach: Assert registry/index/sub-navigation presence; verify all three windows and definitions from seeded aggregates; verify anonymous, identity-hint-only, and approved non-root denial; verify server-authenticated root access and unavailable-state rendering.
- Risks or open questions:
  - Impact: reusing a display-only identity hint would expose operator data.
  - Early warning / validation: test a forged/identity-hint-only request separately from an authenticated session.
  - Mitigation: use the server-authenticated root-approval predicate for the route, not the hint-based read-time resolver.
- Canonical components/API contracts touched: `ToolsPageSupport::registry()`/`navOptions()`, `ToolsPageController`, the server-authenticated viewer policy, and a new Visitor Statistics page template.

## Stage 4

- Goal: Validate release behavior and operator interpretation in a deployment-like configuration.
- Dependencies: Stages 1–3.
- Expected changes: Add focused end-to-end coverage across request collection and the protected Tools page; add the minimum operator-facing configuration/coverage note required to locate the private state and understand excluded traffic.
- Verification approach: Run the relevant focused tests and full suite; exercise a writable private-state path with static and dynamic eligible pages; confirm the state is not downloadable, published, or included in generated artifacts.
- Risks or open questions:
  - Impact: a correct local implementation may silently fail under host permissions or static publishing.
  - Early warning / validation: use a deployment-like private-state directory and artifact build during verification.
  - Mitigation: surface unavailable state, retain fail-open collection, and document the required state-path check.
- Canonical components/API contracts touched: existing test/host configuration conventions, static-artifact boundary, and the visitor-statistics page contract.
