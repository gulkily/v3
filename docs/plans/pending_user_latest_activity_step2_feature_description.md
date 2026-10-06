> **Feature plan:** [Step 1](./pending_user_latest_activity_step1_solution_assessment.md) · [Step 2](./pending_user_latest_activity_step2_feature_description.md) · [Step 3](./pending_user_latest_activity_step3_development_plan.md) · [Step 4](./pending_user_latest_activity_step4_implementation_summary.md)

# Step 2: Feature Description — Pending User Latest Activity

## Problem

The approved-only `/users/pending` queue identifies pending profiles but gives reviewers no at-a-glance indication of what each identity most recently did or when.

## User Stories

- As an approved reviewer, I want to see a pending user's latest activity and its timestamp below that user so that I can assess an approval request without leaving the queue.
- As an approved reviewer, I want the activity text to open its item page and the newest pending activity first so that I can prioritize and inspect requests efficiently.
- As an approved reviewer, I want bootstrap-only identities to show their account-bootstrap activity so that new accounts are not mistaken for inactive or missing data.

## Core Requirements

- Each pending profile has one subordinate display line containing its most recent indexed activity description as a link to its canonical item page and the site's standard reading-friendly timestamp, with no `Latest activity:` prefix.
- The subordinate line spans the user and profile columns so its reading-friendly activity text has sufficient room; the approval control remains separate.
- Account-bootstrap activity is eligible and visible when it is the newest activity.
- Pending profiles are listed in reverse-chronological latest-activity order, with a stable tie-breaker and no-activity profiles last.
- A pending profile with no indexed activity shows a clear safe fallback rather than a blank or misleading timestamp.
- The queue remains visible only to approved viewers, and the existing approval action and its pending/success/failure behavior remain unchanged.

## Delivery Scope

- **Work type:** Application change.

## Completion Boundary

- **Normal entry:** An approved viewer opens `/users/pending`.
- **End-to-end outcome:** Every listed pending profile has a reverse-chronologically ordered activity-description link and reading-friendly timestamp below it, sourced from its latest indexed activity.
- **Recovery:** Missing activity data renders the defined fallback at the end of the queue; authorization and the server-authoritative approval flow behave as they do now.
- **Release condition:** Route coverage proves bootstrap-only and later-activity cases, order, item destinations, access control, and existing approval interactions.

## Risks

- **Hidden bootstrap activity is omitted:** Validate a bootstrap-only pending identity first; include all relevant indexed activity, not only normal public-feed items.
- **Latest activity is nondeterministic on tied timestamps:** Validate stable ordering before implementation planning; use the activity system's established stable ordering contract.
- **Wrong item destination:** Validate bootstrap and later activity links against their canonical item pages; reuse existing activity destination semantics.
- **Table layout or approval controls regress:** Validate both a populated queue and approval-action behavior with existing pending-directory coverage.

## Shared Component Inventory

- **Pending approval queue:** Extend the existing `/users/pending` table and retain its canonical approval controls.
- **Activity presentation/data:** Reuse the existing activity description, destination, ordering, and timestamp semantics; no new activity API or parallel activity model.
- **Pending-approval browser behavior:** Reuse unchanged; this feature adds server-rendered context only.

## Simple User Flow

1. An approved viewer opens `/users/pending`.
2. The viewer sees pending profiles with newest activity first and can open each activity item from its description.
3. The viewer can approve a profile using the existing control.

## Success Criteria

- A bootstrap-only pending profile displays its bootstrap activity and the standard reading-friendly timestamp.
- A pending profile with later activity displays that later activity instead.
- Activity text links to the corresponding canonical item page.
- Pending profiles sort newest activity first, with no-activity profiles last.
- No line begins with `Latest activity:`.
- Existing access-control and approval-queue behavior remain verified.
