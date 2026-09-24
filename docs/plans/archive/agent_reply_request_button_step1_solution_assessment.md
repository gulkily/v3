# Agent Reply Request Button Step 1 Solution Assessment

## Problem Statement

Users need an intentional in-page control that requests a `reply-agent` response for a specific post without relying on automatic browser-triggered reply work.

## Option A: Add a request button on eligible post cards

Pros:
- Directly matches the desired user workflow: find a post, click a button, see the result.
- Reuses the existing per-post card context and feedback area.
- Keeps scope centered on manual requests instead of adding cron or broader automation.
- Can preserve existing analysis, gate, idempotency, and posting semantics.

Cons:
- Requires a clear rule for who can see and use the button.
- Needs careful skipped/failure feedback so users understand when no reply is posted.
- Card actions can become crowded unless the UI is restrained.

## Option B: Add a separate operator surface for agent response requests

Pros:
- Keeps post cards simpler for regular browsing.
- Provides a more controlled place for operator-only actions and status.
- Leaves room for future retry, audit, or moderation workflows.

Cons:
- Adds navigation and workflow distance from the target post.
- Feels heavier than the core request: one button near a post.
- Risks designing an operator tool before the basic manual request behavior is proven.

## Option C: Implement the broader manual-plus-cron control split first

Pros:
- Addresses manual requests and future scheduled automation together.
- Creates clearer long-term separation between automatic and manual agent reply settings.
- Could reduce later refactoring if cron automation is still a near-term goal.

Cons:
- Larger feature than the immediate user-clicked request.
- Reopens configuration, worker, and operational questions that are not required for the button.
- Increases planning and testing surface before validating the primary UI behavior.

## Recommendation

Recommend Option A.

Brief justification:
- The main user value is an explicit request button close to the post.
- The application already has per-post rendering, analysis feedback, and an idempotent reply-generation path to build on.
- Cron and broader operator workflows should remain separate follow-up features unless Step 2 uncovers a hard dependency.
