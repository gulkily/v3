# Private message conversation list Step 2 feature description

> **Feature plan:** [Step 1](./private_message_conversation_list_step1_solution_assessment.md) · [Step 2](./private_message_conversation_list_step2_feature_description.md) · [Step 3](./private_message_conversation_list_step3_development_plan.md) · [Step 4](./private_message_conversation_list_step4_implementation_summary.md)

## Problem

Inbox and Sent repeat messages, hide older counterparts, and lack a New message action. Members need a complete conversation list that preserves privacy.

## User stories

- As an approved member, I want one row per counterpart so that I can find our latest exchange.
- As an approved member, I want to select or enter a username so that I can start a conversation from Messages.

## Core requirements

- Make `/messages` the authenticated destination; redirect Inbox/Sent pages there without a Sent filter. Align titles/navigation; add a conversation back link and linked counterpart heading.
- Show one linked row per counterpart across both directions, newest activity first: username, latest verified one-line preview, friendly local time with exact machine-readable and hover timestamps. Decrypt and verify locally; retain rows when previews fail.
- Provide bounded Load more, retry, and end states. Paging preserves the opening list without omissions or duplicates, including tied times; refresh or return from a conversation shows new activity.
- Add New message with directory autocomplete and direct username entry. Validate approved keys independently of directory visibility; reject self, invalid, or unavailable recipients while preserving input. Show "No messages yet" with New message when empty.
- Reuse existing conversations/composers, key coverage, and drafts. Retain historical rows when recipient eligibility changes; explain unavailable conversations and provide a back link.

## Delivery scope

Work type: application change. Excludes chat refinements, send-without-reload, older transcript retrieval, unread tracking, and live incoming updates.

## Completion boundary

Deliver the normal flow below with explanatory failures: retry list loads and recoverable preview failures, preserve recipient input and failed-send drafts. Release requires keyboard/mobile usability, authorized access only, and private-message exclusion from public/static/offline output.

## Risks

- **Disclosure:** unauthorized or unverified content could appear. First validate unauthorized viewers and mixed signatures; mitigate through authenticated retrieval and verified-only local previews without persistent plaintext or verification results.
- **Pagination drift:** activity could hide/repeat counterparts. First validate tied times and intervening arrivals across pages; mitigate with the opening-list boundary defined above.
- **Eligibility mismatch:** valid recipients or historical rows could disappear. First validate directory-absent recipients and lost eligibility; mitigate by separating suggestions from validation and retaining rows.

## Shared component inventory

- Inbox/Sent pages, conversation, top navigation: extend navigation; replace message cards with shared counterpart-summary rows.
- Mailbox service and Inbox/Sent/conversation APIs: preserve contracts; extend with conversation-list retrieval.
- Local reader and recipient-key API: reuse verification/key resolution; extend for previews/recovery.
- Conversation/profile/aggregate-user composers: reuse shared composer and encryption behavior.
- Directory/composite-user profiles: reuse suggestions/destinations; add the missing mailbox recipient selector.

## Simple user flow

1. Open Messages and browse or load more conversations.
2. Select a row, or use New message to choose or enter an eligible username.
3. Read and send through the existing conversation; recover from feedback if needed.
4. Return to Messages and see the latest activity.

## Success criteria

- Three list pages include every authorized counterpart exactly once, covering sent-only, received-only, tied-time, older activity, and intervening arrivals.
- Legacy links reach Messages; first sends create rows and replies update previews/order after returning.
- Empty, invalid-recipient, unavailable-key, bad-signature, and failed-load/send cases meet recovery rules; unauthorized access discloses nothing.
- Keyboard/mobile checks and existing composer, encryption, verification, and release-isolation checks pass.
