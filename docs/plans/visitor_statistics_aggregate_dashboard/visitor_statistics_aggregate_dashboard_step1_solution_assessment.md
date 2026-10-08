> **Feature plan:** [Step 1](./visitor_statistics_aggregate_dashboard_step1_solution_assessment.md) · [Step 2](./visitor_statistics_aggregate_dashboard_step2_feature_description.md) · [Step 3](./visitor_statistics_aggregate_dashboard_step3_development_plan.md) · [Step 4](./visitor_statistics_aggregate_dashboard_step4_implementation_summary.md)

## Original Query

Sounds good, please write Step 1 of `docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md`.

## Understood Intent

Plan the next Visitor Statistics slice: a more useful operator dashboard that keeps aggregate-only privacy guarantees and distinguishes anonymous from server-authenticated traffic.

## Problem

The current daily aggregate table cannot accurately present rolling periods or traffic trends, and it does not show the useful anonymous versus server-authenticated split.

## Options

### Option A: Presentation-only refresh

- Reformat the existing three-window table and improve explanatory copy.
- Pros: small change; preserves the existing data model unchanged.
- Cons: cannot provide a true rolling 24-hour view, trends, or the requested traffic split.

### Option B: Privacy-preserving aggregate dashboard

- Retain bounded hourly and longer-period aggregate measures, including anonymous and server-authenticated request totals, without retaining visitor, session, key, route, or referrer records.
- Pros: supports truthful time windows and trends; gives operators the requested segmentation; preserves the existing first-party aggregate privacy boundary.
- Cons: requires a careful metric contract and migration from the current daily-only aggregate state; distinct-client segment estimates cannot be summed when a visitor changes authentication state.

### Option C: Session or event analytics

- Retain session-, identity-, or request-level events for detailed funnels, paths, and per-user analysis.
- Pros: supports richer reporting and exact behavioral analysis.
- Cons: creates a tracking system, conflicts with the current privacy promise, and is disproportionate to an operator statistics page.

## Recommendation

Choose **Option B**. Deliver a small vertical slice: a protected aggregate dashboard with a truthful recent-period selector, an accessible visits-over-time view, and anonymous versus server-authenticated request counts. Keep the table as fallback and retain no raw identifiers or histories. Defer sessions, per-user reporting, paths, referrers, and device analysis.
