> **Feature plan:** [Step 1](./pending_approval_activity_order_step1_solution_assessment.md) · [Step 2](./pending_approval_activity_order_step2_feature_description.md) · [Step 3](./pending_approval_activity_order_step3_development_plan.md) · [Step 4](./pending_approval_activity_order_step4_implementation_summary.md)

## Problem

On Users Awaiting Approval, each user's most recent action appears after their Approve button. Mobile stacking visually separates the activity from its user, making it appear to belong to the following user.

## User Stories

- As an approved user, I want to read each pending user's most recent action before their Approve button so that I can confidently associate the activity and approval with the correct person.

## Core Requirements

- On mobile and desktop, present each user as one clearly bounded group in the reading order: user details, most recent action, Approve.
- User-requested refinement: use User, Recent activity, and Approve columns. User contains the linked username with `openpgp-` plus the first ten key characters and `...` beneath it; Recent activity contains the latest activity and timestamp.
- Keep visual and assistive reading order aligned, with usable keyboard navigation.
- Preserve existing profile links, activity text, post links, timestamps, and the no-activity fallback.
- Preserve approval permissions, signing, progress feedback, and failure recovery; successful approval removes the complete correct user group and shows the empty state when appropriate.

## Delivery Scope

**Work type: application change.** Apply approved Option A to the pending users page using its existing data and approval flow. Scope covers presentation and any adjustments needed to preserve approval interactions; activity selection and other pages' behavior remain unchanged.

## Completion Boundary

An approved user enters through the Users directory, reviews a pending user's activity, and approves that user. Success removes only that user's complete group; failure retains their details and activity, displays feedback, and enables retry. Release requires verified mobile/desktop grouping, reading order, and approval success/failure behavior.

## Risks

- **Incorrect removal:** Layout changes could leave activity behind or remove a neighbor. Earliest validation: review existing approval cleanup during Step 3 planning. Mitigation: keep each complete user group associated with its approval and verify adjacent-user and last-user cases.
- **Responsive or reading-order regression:** Long content could obscure grouping or diverge from assistive reading order. Earliest validation: review the responsive layout and reading sequence during Step 3 planning. Mitigation: use one consistent order and verify narrow/wide layouts with long and missing activity.

## Shared Component Inventory

- **Users directory:** Reuse the existing pending-users entry link.
- **Pending users page:** Extend its canonical presentation and reuse its existing profile/activity data, links, timestamps, feedback, and empty state; no parallel component is needed.
- **Profile page approval:** Shares the browser approval helper; preserve its existing behavior.
- **Browser signing and approval APIs:** Reuse the existing approval contract without changes.

## Simple User Flow

1. Open Users, then View users awaiting approval.
2. Read one user's details and most recent action, then select their Approve button.
3. See success and the updated list, or failure feedback with the same user available for retry.

## Success Criteria

- At mobile and desktop widths, every displayed activity or fallback precedes its user's Approve control within the same clear group, including adjacent users and long content.
- Successful approval removes exactly the selected user's complete group; approving the last user displays the empty state.
- Failed approval retains the correct group and enables retry; existing links and keyboard navigation remain usable.

**Review:** Approved Step 2 received; this scope is the basis for Step 3.
