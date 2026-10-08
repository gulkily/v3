> **Feature plan:** [Step 1](./visitor_statistics_step1_solution_assessment.md) · [Step 2](./visitor_statistics_step2_feature_description.md) · [Step 3](./visitor_statistics_step3_development_plan.md) · [Step 4](./visitor_statistics_step4_implementation_summary.md)

# Step 2: Feature Description — Visitor Statistics

## Problem

An operator has no consistent in-product view of the people and browser clients reaching an instance. Infrastructure logs are deployment-specific and do not safely distinguish readership from user identity.

## User Stories

- As a root-approved operator, I want a compact visitor-statistics page so that I can gauge recent instance use without inspecting server logs.
- As a root-approved operator, I want client and authenticated-user measures clearly separated so that I do not mistake browser traffic for member activity.
- As a visitor or member, I want usage measurement to avoid exposing my browsing history or identity to other users so that basic operations do not create a tracking system.

## Core Requirements

- Add one root-approved-operator-only Tools destination showing the last 24 hours, 7 days, and 30 days of server-observed eligible page visits, distinct clients, and distinct authenticated users; each measure has a plain-language definition.
- Report only first-party aggregates: no visitor/user lists, IP addresses, user agents, requested paths, referrers, or cross-site/third-party analytics.
- Measure eligible human page visits only; exclude static assets, API traffic, and recognizable automated traffic, and clearly state that edge-cache and infrastructure-only traffic is outside its coverage.
- Retain only the minimum bounded aggregate data needed for the displayed periods; do not retain a per-client or per-user visit history.
- When statistics are unavailable, incomplete, or still initializing, render an honest status and preserve normal Tools navigation and access behavior.

## Delivery Scope

- **Work type:** Application change.

## Completion Boundary

- **Normal entry:** A root-approved operator opens Visitor Statistics from `/tools/`.
- **End-to-end outcome:** The operator sees clearly labeled 24-hour, 7-day, and 30-day aggregate client and authenticated-user measures, plus their coverage and privacy limits.
- **Recovery:** A missing, incomplete, or newly initialized data set shows a safe explanatory state; unauthorized viewers receive the established forbidden behavior and no statistics.
- **Release condition:** Automated route, authorization, aggregation-window, exclusion, privacy-boundary, and unavailable-data coverage passes.

## Risks

- **Misleading “unique client” counts:** Validate metric definitions and repeat-visit behavior before Step 3; label the measure and its precision/coverage plainly.
- **Statistics become a browsing-history store:** Validate retained fields and expiry behavior first; allow only aggregate bounded data and no inspectable visitor records.
- **Confidential operational data is exposed:** Validate anonymous, approved non-root, and root-approved access before UI planning; reuse the established root-approval boundary server-side.
- **Caching or automation distorts interpretation:** Validate an eligible browser page visit, an asset/API request, and recognizable bot traffic; publish coverage limits on the page.

## Shared Component Inventory

- **Tools registry, index, and sub-navigation:** Extend `ToolsPageSupport` as the single canonical destination source; do not create a parallel operator menu.
- **Root-approved access policy:** Reuse the existing root-approved identity rule used for operator-level feature-flag changes; apply it to viewing, not only navigation.
- **Authentication/profile identity data:** Reuse existing authenticated identity semantics solely for aggregate authenticated-user counting; do not expose profile details or create a visitor API.
- **Existing user directories/profile counts:** Do not reuse as visit statistics—they describe registered identities and authored content, not observed readership.

## Simple User Flow

1. A root-approved operator opens Tools and selects Visitor Statistics.
2. The operator reviews the three recent windows and their client/user definitions.
3. If data is not ready, the operator sees its scope and recovery status rather than fabricated counts.

## Success Criteria

- A root-approved operator can find and read all three time-window summaries from Tools.
- Each summary distinguishes eligible visits, clients, and authenticated users and states its coverage.
- No page exposes an individual visitor, identity, browsing path, network identifier, or third-party analytics data.
- Anonymous and approved non-root viewers cannot retrieve the statistics.
- Empty or initializing data is visibly distinguishable from zero activity.
