> **Feature plan:** [Step 1](./member_directory_access_split_step1_solution_assessment.md) · [Step 2](./member_directory_access_split_step2_feature_description.md) · [Step 3](./member_directory_access_split_step3_development_plan.md) · [Step 4](./member_directory_access_split_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

The users page/subpages currently do two separate things: member directory and user/access management. I think we should split them, and move the access management into under tools.

## Problem

Member browsing shares navigation and detail pages with pending approvals and account access information, making two different tasks compete.

## Option A — Move pending approvals only

Put the pending approval queue under Tools and remove its entry points from the standard and Forte directories, retaining the existing member detail pages.

- Pros: Small change; preserves familiar member pages and links.
- Cons: Account key history, unapproved profiles, and approval actions remain reachable through member detail pages, so the separation is incomplete.

## Option B — Separate directory and access workflows (recommended)

Keep Users as the member directory, with member activity and public profile information. Add Tools → User Access for pending approvals and the account/profile/key information needed to review access. Apply the same boundary to standard and Forte views and their subpages.

- Pros: Clear destinations for browsing members and managing access; covers the full journey from the Tools entry to approval review and outcome.
- Cons: Requires reviewing shared profile pages and old links so approval and recovery flows remain usable.
- Boundary: Retain useful public attribution and member activity in the directory; move access review/actions and detailed account key history into the management flow. Preserve existing authorization rules and signed approval behavior.

## Recommendation and scope

Choose Option B as one releasable slice: browse members through Users; find pending users, review the relevant identity, and complete existing approval actions through Tools → User Access. Preserve old management links with an appropriate compatibility path and retain visible errors/recovery actions. Keep labels short and place explanations in contextual help. Track affected copy in the UI copy audit checklist during implementation.

Do not add new access powers, change approval policy, redesign personal account setup, or require a database migration. Step 2 should establish the precise boundary for shared public profile pages and key history, plus legacy links, before implementation planning.

## Evidence reviewed

- Standard Users links to `/users/pending/`; that page exposes Approve actions.
- Forte Users mixes member filters with Not Approved; its member detail links to account key history.
- Standard and Forte username pages list approved and unapproved profiles; individual profile pages expose approval review.
- Tools has a shared destination registry; Users also has static artifacts and separate host routing that need continuity.

## Approval

Approved Step 1 received October 18, 2026. Option B advances to Step 2; implementation remains gated on Approved Step 3.
