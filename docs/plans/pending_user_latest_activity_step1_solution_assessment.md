# Step 1: Solution Assessment — Pending User Latest Activity

## Original Query

On the `/users/pending` page, I want to see one line below each user that has their most recent activity (even if it's just an account bootstrap) and a timestamp. Please write Step 1 of `docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md`.

> **Feature plan:** [Step 1](./pending_user_latest_activity_step1_solution_assessment.md) · [Step 2](./pending_user_latest_activity_step2_feature_description.md) · [Step 3](./pending_user_latest_activity_step3_development_plan.md) · [Step 4](./pending_user_latest_activity_step4_implementation_summary.md)

## Problem Statement

Approved viewers cannot tell when or what a pending identity last did from the `/users/pending` approval queue.

## Option A: Use each profile's bootstrap record only

- Show the bootstrap label and its creation time below each pending row.
- **Pros:** Small, identity-specific lookup; guarantees a line for valid bootstrapped profiles.
- **Cons:** Becomes stale as soon as the person creates a later post or other activity; does not meet “most recent activity.”

## Option B: Reuse the activity read model per pending profile

- Add each pending profile's latest activity label and timestamp to the existing pending-directory data, including bootstrap activity.
- Render a subordinate line directly beneath its table row.
- **Pros:** Matches the requested meaning of “most recent”; includes bootstrap-only identities; uses the existing derived data without a schema change; preserves the current approved-viewer access boundary.
- **Cons:** Requires a clear empty-state label for an unexpected pending profile with no indexed activity.

## Option C: Link each row to the profile or Activity page

- Keep the queue unchanged and add a link for reviewers to find activity elsewhere.
- **Pros:** Minimal new data and markup.
- **Cons:** Adds navigation and does not provide the requested at-a-glance line or timestamp.

## Recommendation

Choose **Option B** as a small vertical slice: show `{activity description} — {reading-friendly timestamp}` immediately below every pending user, with account bootstrap eligible as the latest activity. Keep the existing approval control and access rules unchanged; display a safe “No recorded activity” fallback only if the read model has no activity for a pending profile.
