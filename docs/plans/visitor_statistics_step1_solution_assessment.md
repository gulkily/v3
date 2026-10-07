> **Feature plan:** [Step 1](./visitor_statistics_step1_solution_assessment.md) · [Step 2](./visitor_statistics_step2_feature_description.md) · [Step 3](./visitor_statistics_step3_development_plan.md) · [Step 4](./visitor_statistics_step4_implementation_summary.md)

## Original Query

As an operator, I'd like to see basic stats about clients/users visiting my instance.

## Understood Intent

Give an instance operator a simple, privacy-conscious view of usage without defining the exact metrics, retention, or presentation yet.

## Problem

Operators cannot currently assess basic instance use from the application without independently inspecting infrastructure data.

## Options

### Option A: Infrastructure access-log reporting

- Derive statistics from the reverse proxy or web-server logs.
- Pros: no application changes; captures all HTTP traffic; can use existing operational tooling.
- Cons: varies by deployment; mixes people with bots and assets; exposes IP-address handling and has no reliable user identity context.

### Option B: First-party, privacy-preserving aggregate statistics

- Record only bounded aggregate visit and participation measures in the instance, surfaced to its operator.
- Pros: consistent across deployments; can distinguish anonymous clients from known users where appropriate; avoids per-visitor histories and third-party sharing.
- Cons: needs clear definitions for unique/repeat visits, bot handling, retention, and operator access; may not capture traffic that never reaches the application.

### Option C: Optional external analytics integration

- Send or expose events to a configured analytics service.
- Pros: mature dashboards, segmentation, and reporting with little custom UI.
- Cons: adds vendor and deployment dependency; raises privacy, consent, and data-egress concerns; weak fit for a self-hosted instance baseline.

## Recommendation

Choose **Option B** as a small vertical slice: show operators a few aggregate, time-bounded client and signed-in-user counts, with no individual visitor profiles. It produces a portable in-product outcome while preserving infrastructure logs as an optional operational complement. Step 2 should settle the metric definitions, privacy/retention policy, bot treatment, and operator-facing location before planning implementation.
