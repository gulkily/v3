# Private message history Step 2 feature description

> **Feature plan:** [Step 2](./private_message_history_step2_feature_description.md) · [Step 3](./private_message_history_step3_development_plan.md) · [Step 4](./private_message_history_step4_implementation_summary.md)

## Problem

Conversations expose only their newest 25 messages, leaving older history unreachable. Loading more must preserve the reader's place despite asynchronous decryption, message grouping, and new replies.

## User stories

- As a participant, I want to load older messages so that I can recover earlier context.
- As a reader, I want my visible message to stay in place so that loading history does not interrupt reading.
- As a participant, I want failed loads to be retryable so that I can continue without losing messages or my draft.

## Core requirements

- Keep the initial window at 25 messages and offer an explicit Load older control with bounded pages. Show loading, failure/retry, and exhausted-history states; prevent overlapping requests.
- Retrieve both directions of the authorized conversation with deterministic chronological ordering, including equal timestamps. A stable browsing session must neither omit nor duplicate older messages when arrivals or local replies occur between requests.
- Prepend older messages while preserving the visible reading anchor through decryption and layout changes. Respect subsequent user navigation; keep sender groups and local-date separators correct across page boundaries without replaying initial-scroll behavior or stealing focus.
- Reuse browser decryption, signature verification, message rendering, and exact/friendly timestamps. Unreadable messages remain represented without blocking pagination or exposing unverified plaintext; retry uses the correct loaded envelope rather than the newest-message window.
- Preserve loaded messages, pagination position, and editable drafts on recoverable load failure. Explain unusable pagination state and provide an explicit restart path. Retain inline sending, latest-message navigation, composer recovery, and privacy boundaries.

## Delivery scope and completion boundary

Work type: **application change**, building on Cycle 2. Entry is Messages → conversation → Load older; completion is reaching all stored history available to that authorized viewer, with recoverable loading and stable reading position. Release requires focused tests and an isolated browser journey. Step 1 is skipped because the master checklist already specifies bounded pagination and explicit loading. Unread state, live updates, new-key access to old ciphertext, and the [decryption-widget follow-up](./private_messaging_usability_master_checklist.md#deferred-follow-ups) remain outside this cycle. History retrieval does not guarantee that the current key can decrypt it.

## Risks

- **Missing, duplicated, or disclosed history:** first validate tied timestamps, interleaved arrivals, and foreign pagination state; require stable conversation/viewer-scoped continuation before UI integration.
- **Reading-position jumps:** test slow/mixed decryption, tall messages, user scrolling during loading, and mobile composer fallback early; preserve a visible message anchor without overriding later navigation.
- **Stale reader data or broken sends:** first exercise old-message retries and sending during history loading; retain page-specific envelopes, independent send state, and compatible existing API behavior. Keep key-recovery redesign deferred.

## Shared component inventory

- Extend the conversation page/controller and `private_message_conversation.js`/styles with Load older; reuse the canonical message partial, shared reader, and `message_time.js`.
- Extend the conversation API/service/store pagination contract; preserve existing no-cursor callers, inbox/sent APIs, conversation-list summaries, and recipient-key lookup. Reuse established pagination conventions where applicable.
- Preserve Messages/New message navigation, list previews, retained mailbox rendering, shared composer/send recovery, and profile/aggregate-user entry points. No separate history renderer or crypto implementation.

## Simple user flow

1. Open a conversation from Messages and read the recent window.
2. Load older messages, retaining reading position; repeat until history is exhausted.
3. Retry a failed page or unreadable message, or return to the latest message and reply without reloading.

## Measurable success criteria

- Traverse at least three pages, including equal timestamps and intervening arrivals, with every eligible message represented exactly once.
- Keep the same visible message anchored after loading/decryption, including narrow viewports and concurrent sending; preserve user navigation and drafts.
- Verify retry/exhaustion/restart, mixed verification results, unauthorized access rejection, existing-client compatibility, and static/offline exclusion through automated and browser checks.
