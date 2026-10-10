# Private message chat refinement Step 2 feature description

> **Feature plan:** [Step 1](./private_message_chat_refinement_step1_solution_assessment.md) · [Step 2](./private_message_chat_refinement_step2_feature_description.md) · [Step 3](./private_message_chat_refinement_step3_development_plan.md) · [Step 4](./private_message_chat_refinement_step4_implementation_summary.md)

## Problem

Tall message cards and a large composer make conversations cumbersome. Sending reloads the page, while failed reads and uncertain delivery lack reliable recovery.

## User stories

- As a participant, I want compact, clearly attributed messages so that I can follow the conversation.
- As a correspondent, I want replies to appear without reloading so that I can keep writing and reading.
- As a sender, I want recoverable failures so that I retain my draft without accidentally sending duplicates.

## Core requirements

- Compact directional messages retain accessible sender cues, consecutive-sender grouping, local-date separators, friendly local times, and exact timestamps. Long and multiline messages remain readable.
- Use normal document scrolling with a sticky composer where space permits and an inline fallback when necessary. Initially reveal the newest message after decryption settles unless the user has begun navigating; preserve earlier reading position and provide a way back to the latest reply. Do not steal focus or open mobile keyboards automatically.
- Provide an accessibly named, initially two-to-three-row growing composer, a muted encryption note below, and no redundant visible headings. Ctrl/Cmd+Enter sends; Enter and Shift+Enter insert newlines. Respect input-method composition.
- Append confirmed replies without reloading, with authoritative identity and time. Preserve failed drafts and text entered during a pending send. Retrying an uncertain attempt must recognize prior acceptance without duplicates; changed content is a separate attempt and cannot impersonate the original.
- Preserve browser-only encryption and verified-only message display. Delivery confirmation is not signature verification. Correct “Signature verified,” keep success quiet and failures prominent, and offer isolated retry for recoverable read failures without disrupting other messages or adding persistent transcript caches.

## Delivery scope and completion boundary

Work type: **application change**. Entry is Messages → existing or new conversation; completion is reading recent verified messages, sending an inline reply, and recovering failed reads or uncertain sends. Release requires automated and browser evidence, including shared-composer compatibility. Retain the initial history bound; older-history retrieval, unread tracking, and live incoming updates remain deferred. Rescope before implementation if this exceeds one day or eight stages.

## Risks

- **Duplicate delivery or lost drafts:** first validate lost-confirmation retries and edits during sending; establish safe attempt recovery before integrating inline replies.
- **Obscured messages or scroll jumps:** validate mobile keyboards, zoom, long drafts, and delayed decryption early; use responsive fallback and navigation-aware scrolling.
- **False trust or shared-surface regressions:** exercise invalid/missing signatures and unavailable keys first; retain shared verification and test every composer entry point.

## Shared component inventory

- Reuse the conversation page and shared styles; preserve Messages list/New message navigation, counterpart profile links, and legacy mailbox redirects.
- Extend `private_message_composer.php` and `private_message_compose.js`; preserve profile and aggregate-user composers.
- Extend `private_message_reader.js`; retain compatibility with the retained mailbox template and list previews in `private_message_list.js`. Reuse `message_time.js`.
- Preserve shared contracts for conversation, inbox/sent, conversation-list, and recipient-key APIs. Extend the shared send API/service/store recovery contract consistently across entry points. No parallel composer, reader, or crypto implementation.

## Simple user flow

1. Open a conversation from Messages and read locally verified recent messages.
2. Write and send a reply; see its confirmed entry without leaving the conversation.
3. Recover a failed read or uncertain send while retaining draft text and reading position.

## Measurable success criteria

- Compare identical transcripts/viewports; target three-to-four times as many short messages without sacrificing legibility.
- Confirm stable ordering, including same-second replies, no successful-send reload, and no duplicate after a lost-confirmation retry.
- Verify draft preservation, isolated read retry, keyboard/zoom/mobile usability, and profile/aggregate-user compatibility with focused tests and browser checks.
