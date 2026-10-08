> **Feature plan:** [Step 1](./visitor_statistics_aggregate_dashboard_step1_solution_assessment.md) · [Step 2](./visitor_statistics_aggregate_dashboard_step2_feature_description.md) · [Step 3](./visitor_statistics_aggregate_dashboard_step3_development_plan.md) · [Step 4](./visitor_statistics_aggregate_dashboard_step4_implementation_summary.md)

# Step 2: Feature Description — Aggregate Visitor Statistics Dashboard

## Problem

The current visitor-statistics page reports only daily aggregates in a three-row table, making its “24 hours” label inaccurate and leaving operators unable to see traffic trends or the anonymous versus server-authenticated request mix.

## User Stories

- As a root-approved operator, I want truthful recent visitor periods and a trend view so that I can understand instance activity without reading server logs.
- As a root-approved operator, I want anonymous and server-authenticated requests separately reported so that I can distinguish readership from signed-in use.
- As a visitor or member, I want statistics to remain aggregate-only so that operational reporting does not create a browsing or identity tracking system.

## Core Requirements

- Keep Visitor Statistics root-approved-operator-only and report only server-observed eligible page requests.
- Provide a selector for the last 24 hours, 7 days, 30 days, and 90 days, with a truthful visits-over-time view and an accessible aggregate-table fallback.
- For every selected period, show eligible requests and its anonymous versus server-authenticated split; describe server-authenticated as a verified server-side session, not a supplied key or identity hint.
- Retain only bounded aggregate measures needed for the selected periods; do not retain visitor, session, public-key, user, path, referrer, user-agent, or network records.
- Clearly define coverage, exclusions, estimate limitations, collection start, and initializing/unavailable states.

## Delivery Scope

- **Work type:** Application change.

## Completion Boundary

- **Normal entry:** A root-approved operator opens Visitor Statistics from Tools and selects a recent period.
- **End-to-end outcome:** The operator sees accurate aggregate traffic totals, a trend, and anonymous/server-authenticated request counts for that period.
- **Recovery:** Initializing, unavailable, and insufficient-history states explain their limits without fabricating comparisons or exposing statistics to unauthorized viewers.
- **Release condition:** Automated coverage passes for authorization, rolling-window boundaries, aggregate-only persistence, request classification, period rendering, and recovery states.

## Risks

- **A rolling-period label is inaccurate:** Impact: operators act on misleading data. Earliest validation: exercise boundary timestamps before planning. Mitigation: define every period against UTC bucket coverage and label partial history clearly.
- **Segmentation weakens the privacy boundary:** Impact: a dashboard becomes a visitor-tracking system. Earliest validation: inspect persisted state after authenticated and anonymous requests. Mitigation: permit only aggregate request classes; prohibit raw identifiers and histories.
- **Authentication terminology is misunderstood:** Impact: an identity hint or client-held key is mistaken for verified sign-in. Earliest validation: test both server-session and hint-only requests. Mitigation: reuse the existing server-authentication semantics and state them plainly.
- **Charts obscure empty or sparse data:** Impact: absence is misread as zero or a complete trend. Earliest validation: render new, unavailable, and partial-history state. Mitigation: preserve the accessible table and explicit status language.

## Shared Component Inventory

- **Visitor Statistics store and observer:** Extend the canonical aggregate collection/read surface; do not introduce request-event storage or a parallel analytics pipeline.
- **Visitor Statistics controller and page template:** Extend the protected route and its canonical template for period selection, summaries, and recovery states.
- **Tools registry and navigation:** Reuse `ToolsPageSupport`; no parallel operator destination.
- **Root-approved server-authentication policy:** Reuse the established server-side policy for viewing and for classifying authenticated requests; identity hints remain insufficient.

## Simple User Flow

1. A root-approved operator opens Tools and selects Visitor Statistics.
2. The operator selects a recent period and reviews the aggregate trend and anonymous/server-authenticated request totals.
3. The operator can read definitions and coverage limits, or an honest initializing, partial-history, or unavailable state.

## Success Criteria

- A root-approved operator can view each supported recent period from the existing Tools route.
- The page distinguishes total eligible requests from anonymous and server-authenticated requests without presenting them as additive unique-client figures.
- The displayed 24-hour period reflects a rolling period rather than a calendar-day proxy.
- Persisted statistics contain no inspectable visitor, session, key, identity, path, referrer, user-agent, or network record.
- Unauthorized, initializing, unavailable, and partial-history cases remain safe and intelligible.
