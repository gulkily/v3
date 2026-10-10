> **Feature plan:** [Step 1](./pending_approval_activity_order_step1_solution_assessment.md) · [Step 2](./pending_approval_activity_order_step2_feature_description.md) · [Step 3](./pending_approval_activity_order_step3_development_plan.md) · [Step 4](./pending_approval_activity_order_step4_implementation_summary.md)

## Original Query

On the Users Awaiting Approval page, the user's most recent action should come before the Approve button; otherwise, on mobile, it looks like it's part of the following user. Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md.

## Problem Statement

The Users Awaiting Approval page presents each user's most recent action after their Approve button, and mobile stacking separates that activity visually so it can appear to belong to the following user.

## Option A: Consistent information order across screen sizes

Present each pending user as one clearly bounded group ordered as user details, most recent action, then Approve, using that reading order on both mobile and desktop.

- Pros: Directly satisfies the requested order; keeps activity associated with its user; provides a consistent reading and approval flow across screen sizes.
- Cons: Requires a small adjustment to the desktop presentation as well as mobile; approval feedback and removal must continue to apply to the complete user group.

## Option B: Reorder the mobile presentation only

Place the most recent action before Approve within a clearly bounded user group on mobile, retaining the current desktop presentation.

- Pros: Fixes the reported mobile confusion while minimizing visible desktop changes.
- Cons: Creates different presentation orders across screen sizes; requires care to keep visual and assistive reading order aligned; only partially applies the request across the page's layouts.

## Recommendation

Choose **Option A** for a consistent, unambiguous association between each user, their activity, and their approval control.

- **Vertical-slice viability:** A small, independently releasable presentation fix covering entry to the pending users page, review of the correct user's activity, and approval with existing success and failure recovery behavior.
- **Scope:** Preserve existing activity text, links, timestamps, the no-activity fallback, approval permissions, and approval behavior; no new data or database changes are needed.
- **Validation focus:** Review adjacent users on mobile and desktop, including long activity text and users without activity; confirm successful approval removes the complete correct group and failed approval leaves it available for retry.

## Review

**Approved Step 1** received; Option A is the basis for Step 2.
