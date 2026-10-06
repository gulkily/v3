> **Feature plan:** [Step 1](./agent_response_pending_notice_visibility_step1_solution_assessment.md) · [Step 2](./agent_response_pending_notice_visibility_step2_feature_description.md) · [Step 3](./agent_response_pending_notice_visibility_step3_development_plan.md) · [Step 4](./agent_response_pending_notice_visibility_step4_implementation_summary.md)

# Agent Response Pending Notice Visibility Step 1 Solution Assessment

## Original Query

When the agent response feature is activated on a post/comment that has a collapsed footer (consecutive posts by the same author), the notice about the response pending/in progress is collapsed too. I think the notification itself should actually not be collapsed until the whole thing is finished.

## Problem Statement

An active agent-response status is hidden with a same-author continuation’s action footer, leaving readers without visible confirmation until the response finishes.

## Option A: Keep active agent-response status outside the collapsible footer

Pros:
- Keeps pending, in-progress, and other unfinished response states visible on the affected post.
- Preserves continuation merging and ordinary action-footer collapse behavior.
- Limits the change to presentation and existing response lifecycle state.

Cons:
- Requires the status styling to work both inside and outside an action footer.

## Option B: Keep the footer expanded whenever an agent response is unfinished

Pros:
- Reuses the existing status placement.
- Makes the request controls and status visible together.

Cons:
- Exposes all actions for the duration of processing, not just the important notice.
- Weakens the intended compact presentation of same-author runs.

## Option C: Add a separate page-level or transient notification

Pros:
- Can remain visible independently of post layout.
- Could support future multi-request progress reporting.

Cons:
- Duplicates post-scoped lifecycle information and obscures which post it concerns.
- Adds state and dismissal/refresh behavior beyond this focused fix.

## Recommendation

Choose Option A: render an unfinished agent-response notice independently of the collapsible footer, then return to the normal collapsed presentation once the response reaches a terminal state. This is a viable vertical slice because it uses the existing response status and changes only its visibility at the post that initiated the work.
