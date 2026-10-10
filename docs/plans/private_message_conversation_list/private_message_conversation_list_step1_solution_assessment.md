# Private message conversation list Step 1 solution assessment

> **Feature plan:** [Step 1](./private_message_conversation_list_step1_solution_assessment.md) · [Step 2](./private_message_conversation_list_step2_feature_description.md) · [Step 3](./private_message_conversation_list_step3_development_plan.md) · [Step 4](./private_message_conversation_list_step4_implementation_summary.md)

## Original Query

Please go ahead. Thank you.

## Understood Intent

Begin Cycle 1 of the [master checklist](../private_messaging_usability_master_checklist.md): assess how users will find and start conversations from Messages, including encrypted previews, list pagination, and navigation. Later cycles cover chat refinements, older conversation history, and unread tracking.

## Problem Statement

Separate Inbox and Sent lists show individual messages, hide activity beyond their newest 25 entries, and provide no way to start a conversation from the mailbox.

## Option A Group existing mailbox responses in the browser

Combine Inbox and Sent in the browser into counterpart rows, retaining the existing retrieval surfaces and adding recipient selection.

- Pros: reuses existing responses and local decryption; minimizes initial server changes.
- Cons: capped responses omit conversations; complete discovery and reliable pagination require expanding retrieval and browser work beyond this apparent shortcut.

## Option B Derive the conversation list from existing messages

Provide one authenticated, paginated list derived from the viewer's complete message metadata, with one row per counterpart; decrypt and verify each latest-message preview in the browser and reuse existing conversations for sending.

- Pros: complete counterpart discovery without new conversation records; preserves encryption and existing compose behavior; supports bounded loading and a single Messages entry point.
- Cons: needs new list retrieval and preview recovery behavior; ordering must remain predictable when conversations receive new activity during pagination.

## Option C Store separate conversation summaries

Maintain persistent conversation records alongside messages and use them to present and paginate the Messages list, retaining browser decryption for previews.

- Pros: supports future conversation metadata and potentially cheaper list retrieval at scale.
- Cons: adds schema, historical backfill, and synchronization work before unread semantics or demonstrated scale justify it.

## Recommendation

Choose **Option B**. Carry these decisions into Step 2:

- Use `/messages` as the canonical list; redirect old Inbox and Sent page links there and omit a separate Sent filter initially.
- Show linked counterpart rows, latest verified previews, friendly local timestamps, empty state, and bounded Load more navigation. Unavailable previews retain the row with explanatory feedback and retry where useful; never expose unverified plaintext.
- Offer New message with user-list suggestions and direct username entry, validating approved recipient keys independently of directory visibility. Preserve entered text when validation fails.
- Reuse the conversation composer; provide consistent Messages navigation, a back link, and a linked counterpart heading. Preserve existing message APIs and encryption contracts.

This is a releasable vertical slice: Messages → find or choose a counterpart → open the conversation → send → return to the updated list, including list-load and recipient-selection recovery. Step 2 should define pagination during new activity and unavailable-recipient behavior. Keep unread tracking and older transcript retrieval in their scheduled cycles; rescope if planning exceeds the FDP day or eight-stage limit.
