> **Feature plan:** [Step 1](./activity_feed_pagination_step1_solution_assessment.md) · [Step 2](./activity_feed_pagination_step2_feature_description.md) · [Step 3](./activity_feed_pagination_step3_development_plan.md) · [Step 4](./activity_feed_pagination_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/activity_feed_pagination`; [ledger](./effort_estimation_trial.md#cycle-v3activity_feed_pagination), standing enrollment P2.

## Original Query
As a user, I want the activity feeds to be paginated.

## Problem
The standard Activity feed exposes only its initial batch, while the paned feed uses Load more, leaving inconsistent access to older activity.

## Option A — Explicit pages
Show a bounded batch with Older/Newer navigation in Activity views across standard and paned themes; retain the selected filter and applicable sorting in navigable URLs.

- Pros: bounded lists, return navigation, refreshable/shareable positions, and usable standard navigation without JavaScript.
- Cons: replaces paned append behavior; selection, history and feed changes need careful handling. Arbitrary numbered jumps are outside this option.

## Option B — Load more across themes
Extend the existing paned Load more behavior to the standard feed, retaining incremental access to older items.

- Pros: familiar existing interaction; preserves the growing list and paned selection workflow.
- Cons: lists keep growing; returning to a particular batch and browser history require additional behavior.

## Recommendation
**Option A**, using Older/Newer links rather than numbered pages. This directly provides bounded, navigable pages and consistent access to older activity. Deliver it as one vertical slice across existing HTML Activity filters and the paned Commits view, preserving applicable filters, sorting, item access, permission rules and security notices. RSS stays a recent-items feed. Home/board previews and unrelated listings are outside this cycle.

Acceptance should cover first/middle/last/empty pages, invalid or stale positions, equal timestamps, new arrivals, filter/sort resets, browser history, direct links, mobile/desktop layouts and affected themes. Failed navigation must leave a usable recovery path. Confirm these scope assumptions during review; no implementation is authorized.

## Effort snapshots and approval
- Cycle `v3/activity_feed_pagination`, scope v1; starting revision `39be82932fcc36c5017fcf368655ef449bf042da`; reference R1 / protocol P2; Codex, single exposed rater; UTC 2026-10-23.
- Intake: recorded after historical-plan, template, service and controller search exposure; no pristine pre-inspection estimate claimed. Total projection unavailable because the frozen external calibration report/diff sources are unavailable at the recorded location; R1 is retained, with no substitute score.
- Step 1 handoff: total projection remains unavailable for the same reason. No independent rating, measured effort, implementation, completed outcome or maintainer acceptance is claimed.
- Documentation boundary includes this cycle's plans/debug log, later index organization and ledger bookkeeping. Planning writes remain uncommitted.
- Await `Approved Step 1` before Step 2; recommendation may be revised without advancing.
