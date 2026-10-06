> **Feature plan:** [Step 1](./agent_response_pending_notice_visibility_step1_solution_assessment.md) · [Step 2](./agent_response_pending_notice_visibility_step2_feature_description.md) · [Step 3](./agent_response_pending_notice_visibility_step3_development_plan.md) · [Step 4](./agent_response_pending_notice_visibility_step4_implementation_summary.md)

# Agent Response Pending Notice Visibility Step 2 Feature Description

## Problem

Same-author continuation styling collapses a post’s action footer, including the agent-response status. Readers who request a response on that post cannot see its pending or in-progress state until the response reaches a final outcome.

## User Stories

- As a reader who requested an agent response, I want its unfinished status to remain visible on the originating post so that I know the request was accepted and is progressing.
- As a thread reader, I want same-author posts to remain visually compact so that a temporary status notice does not expose unrelated actions.
- As a requester, I want a failed, skipped, or published response to retain its established final presentation so that I can understand the outcome or find the reply.

## Core Requirements

- On a post whose action footer is collapsed as part of a same-author continuation run, show its agent-response status independently while the request is unfinished.
- Treat the existing requested and in-progress lifecycle states as unfinished; restore normal footer-collapse behavior when the request is published, skipped, or fails.
- Preserve the existing status wording, selected response-mode label, published-reply link, authorization, duplicate prevention, and response lifecycle.
- Do not make other footer actions, reaction feedback, analysis disclosures, or Codex-handoff content persistently visible.
- Apply the same status-visibility rule to both root and reply cards wherever continuation styling can collapse their footers.

## Completion Boundary

- **Normal entry:** an eligible reader requests an agent response from a post that is followed by a same-author continuation.
- **End-to-end outcome:** the originating post shows the request’s unfinished notice without requiring hover, focus, or opening the action footer; once a final outcome is reached, it follows the existing completed, skipped, or failed presentation.
- **Needed recovery:** a rejected, duplicate, unavailable, skipped, or failed request continues to show its current unambiguous outcome and does not leave a stale persistent notice.
- **Release condition:** the behavior is verified for root and reply cards, including a request made in-page and a page loaded while a request is unfinished.

## Risks

- **Lifecycle-state mismatch:** an unknown or terminal state could remain visible indefinitely or hide too early. **Earliest validation:** exercise each stored and API-reported status. **Mitigation:** define the unfinished-state set explicitly and retain the existing terminal-status rendering.
- **Dynamic feedback remains hidden:** an in-page request may update the existing feedback element without applying the visibility rule. **Earliest validation:** request a response from a collapsed footer without reloading. **Mitigation:** extend the shared feedback path used by both card types.
- **Continuation regression:** the notice could expose or visually disrupt unrelated footer content. **Earliest validation:** inspect multi-post runs on pointer and touch layouts. **Mitigation:** scope the persistent treatment to the agent-response notice alone.

## Shared Component Inventory

- **Thread-root card:** already renders agent-response status and can have its footer collapsed when it begins a continuation run; extend this canonical surface rather than add a separate notice.
- **Reply post card:** already renders the same status and is the primary continuation-card surface; extend its canonical status presentation.
- **Continuation presentation rules:** currently determine which post footers collapse; extend this shared layout behavior only for an unfinished agent-response notice.
- **Post-interaction browser controller and agent-response API result:** already update the status after an in-page request; reuse their feedback contract so the immediate state matches a loaded page.
- **Page response-status projection:** already supplies stored agent-response lifecycle state to both card surfaces; reuse it without a new API or persistence model.

## Simple User Flow

1. A reader requests an agent response on a root or reply post whose footer is visually collapsed by a same-author continuation.
2. The originating post immediately displays the requested or in-progress status outside the collapsed footer.
3. The reader can continue reading the compact post run while the response is fulfilled.
4. When the response is published, skipped, or fails, the post returns to its existing final-status presentation.

## Success Criteria

- A manual acceptance pass confirms that unfinished statuses remain visible on collapsed root and reply card footers, both after an in-page request and after reload.
- The same pass confirms that unrelated footer actions and feedback remain collapsed.
- Published, skipped, and failed outcomes retain their existing wording and reply-link behavior without a stale persistent notice.
- Existing request eligibility, response mode selection, duplicate prevention, and agent-response fulfillment checks continue to pass.
