# General-Purpose Agent Response Step 2 Feature Description

## Problem

Agent responses currently rely on a classifier's conventional-reply suggestion rather than independently carrying out a user's task. Approved users need to turn a comment into a task instruction about its parent content, including requesting a complete translation of a long post.

## User Stories

- As an approved user, I want to comment an instruction such as "Please translate that entire comment" and request an agent response so that I can get a useful response in the same thread.
- As an approved user, I want the agent to receive the complete parent content so that a translation or transformation does not omit material because it is long.
- As an operator, I want task execution separate from classification so that response quality is not limited by a conventional-reply rubric.
- As a reader, I want the agent result attached to the instruction comment so that the request and its outcome are easy to follow.
- As a requester, I want a clear clarification when my instruction lacks a needed detail so that the agent does not silently guess.
- As an operator, I want general-purpose requests to retain existing authorization, safety, and duplicate-prevention behavior so that broader capability remains controlled.

## Core Requirements

- A request made on a non-agent reply comment interprets that comment as the instruction and its direct parent as the primary referenced content.
- The agent receives the full instruction, primary referenced content, and only the thread context needed to answer it; a translation must preserve the complete referenced content rather than summarize it.
- Task interpretation and generation are owned by the agent-response capability, not by the classifier's suggested-response output or its conventional-reply length limits.
- A completed task result is published as a reply to the instruction comment and remains visibly identified as agent-authored; classification may provide a separate safety or eligibility signal but cannot replace task execution.
- Existing eligibility, authorization, request status, idempotency, and loop-prevention behavior remains in force; direct requests on root posts retain their current response behavior, and ambiguous, unsupported, unsafe, or insufficiently specified instructions produce a clear non-destructive outcome, including a clarification when appropriate.

## Shared Component Inventory

- Post-card action row: extend as the canonical instruction-request surface; it already hosts the request button for reply comments.
- Thread-root action row: retain its canonical direct-response request behavior; no separate task form is needed.
- Agent-response feedback on post and root cards: reuse for queued, clarification, skipped, failed, and published outcomes.
- Existing agent-response request API: extend as the canonical request surface to convey the instruction and its resolved context.
- Existing queued fulfillment and `reply-agent` post rendering: extend as the canonical fulfillment and publication path so results remain threaded and attributable.
- Existing post classifier and its disclosure: retain as a separate diagnostic and safety surface, not as a task authoring or task-generation surface.

## Simple User Flow

1. An approved user replies to a post with a task instruction.
2. The user selects Request agent response on that instruction comment.
3. The system records the request with the comment as instruction and its parent as referenced content.
4. The agent fulfills the task and replies below the instruction, or asks for clarification or reports that it cannot complete the request.
5. The comment card shows the final status and links to any agent response.

## Success Criteria

- An approved user can request a full translation of a parent post through an instruction comment, including content longer than a conventional agent reply.
- The generated translation is a child of the instruction comment and uses the requested language when specified.
- The result is generated from the task instruction and referenced content, not from the classifier's suggested response.
- A request on an ambiguous instruction yields a clear clarification or safe non-completion outcome rather than an unrelated reply.
- Repeated requests do not create duplicate agent responses, and unauthorized users and agent-authored comments remain ineligible.
- Existing direct agent-response requests on root posts continue to work and show their existing outcomes.
