# Agent Reply Request Button Step 2 Feature Description

## Problem

Agent replies currently depend on automatic post-load work or direct API calls, so a user cannot intentionally ask for an agent response from the page they are reading. The feature should add a clear per-post request control that records a durable request, then lets a separate fulfillment path produce and post the agent response.

## User Stories

- As an approved forum user, I want to request an agent response on a specific post so that I can get help from `reply-agent` when it is useful.
- As a reader, I want to see when an agent response was posted so that I can jump to it without scanning the whole thread.
- As an approved forum user, I want a clear skipped or failed message so that I know when the agent did not respond.
- As an operator, I want fulfillment to run separately from the button click so that slow provider work can be handled by a periodic job.
- As an operator, I want manual requests to preserve existing reply gates so that user-triggered requests do not bypass safety or idempotency.

## Core Requirements

- Show an agent-response request button on eligible post cards and thread root cards for authorized users.
- Hide the request button for `reply-agent` posts and posts whose current content already has an agent-response request in progress, fulfilled, skipped, failed, or posted.
- A click must run through an async HTTP request that records the request durably without doing slow provider fulfillment inline.
- Fulfillment must be separate from the click path and suitable for a periodic cron job on production and local dev/test setups.
- Fulfillment must tolerate overlapping periodic invocations without publishing duplicate agent replies for the same requested post content.
- Fulfillment must use existing persistence and reply semantics: SQLite analysis/generated-response state, safety gates, idempotency, and canonical `reply-agent` posting.
- The page must show requested, in-progress, posted/already-posted, skipped, and failed outcomes without exposing private analysis details to unauthorized viewers.

## Shared Component Inventory

- Post card partial: extend as the canonical per-reply action surface rather than creating a separate request widget.
- Thread root card partial: extend in parallel with post cards so root posts and replies expose the same request behavior.
- Existing agent reply feedback placeholder on cards: reuse for request and fulfillment outcome messages.
- Existing `POST /api/generate_agent_reply` route: reuse or adapt as the canonical request API, with fulfillment separated from the button click.
- Existing post analysis and reply-generation result shapes: reuse for skipped, failed, generated, and already-posted outcomes.
- Existing post-analysis browser script: reuse or extend its agent reply request/feedback helpers instead of adding an unrelated client workflow.
- Existing script/cron conventions: use as the operational home for periodic fulfillment rather than relying on a long-running PHP process.
- Existing post-analysis disclosure for approved viewers: do not use as the canonical request UI; it remains diagnostic context, not the action surface.

## Simple User Flow

1. Authorized user opens a thread or post page.
2. Eligible human-authored posts show a compact request button near the existing post actions.
3. User clicks the request button for one post.
4. The button enters a requested state while an async HTTP request records durable request state.
5. A separate fulfillment job processes queued requests, performs any needed analysis, and publishes only when gates pass.
6. Fulfillment persists analysis/generated-response status in SQLite and persists any posted reply as a canonical `reply-agent` post.
7. When the user refreshes or revisits the page, the card shows requested/in-progress status, a link to the posted agent reply, or a skipped/failed outcome.

## Success Criteria

- Authorized users can request an agent response from eligible post cards without using a raw API call.
- Ineligible cards do not show the request button, including cards whose current content already has agent-response request or generated-response state.
- The click path records the request quickly and does not wait for provider analysis or reply posting.
- A periodic fulfillment command can process queued requests in production and local dev/test environments.
- Overlapping fulfillment command runs do not duplicate work or publish duplicate `reply-agent` posts for the same requested post content.
- Repeated clicks or repeated page visits do not create duplicate agent replies for the same post content.
- Outcome feedback is visible on the same card and includes a link when an agent reply exists.
- Existing automatic reply behavior is not expanded by this feature.
