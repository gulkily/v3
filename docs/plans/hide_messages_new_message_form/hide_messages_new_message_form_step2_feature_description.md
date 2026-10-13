> **Feature plan:** [Step 1](./hide_messages_new_message_form_step1_solution_assessment.md) · [Step 2](./hide_messages_new_message_form_step2_feature_description.md) · [Step 3](./hide_messages_new_message_form_step3_development_plan.md) · [Step 4](./hide_messages_new_message_form_step4_implementation_summary.md)

Debug mode: enabled

## Problem

The “New message” recipient form on `/messages/` can be mistaken for registration. Temporarily hiding it removes this confusing interaction while users can still start messages from a recipient’s page.

## User stories

- As a messaging user, I want Messages to show my conversations without a confusing username form so that I can understand the page’s purpose.
- As a sender, I want to message someone from their user page and continue the conversation from Messages so that private messaging remains available.

## Core requirements

- Hide the entire “New message” form and its empty-state button on Messages, for both empty and populated lists; neither remains visible or keyboard-accessible.
- Preserve the empty-state message, conversation rows, previews, unread status, pagination, and existing loading/recovery behavior.
- Preserve sending from username and profile pages and replying within conversations, including eligibility rules and existing send-error recovery.
- Apply the same behavior through existing Messages URLs and legacy entry points that lead there.

## Delivery scope and completion boundary

**Work type:** Application change, following approved Option A. This temporary change adds no replacement guidance, recipient picker, feature toggle, messaging redesign, permission changes, or data migration.

**Complete when:** A user can navigate normally to a recipient’s page, send a message, find it through Messages, and continue the conversation; existing send and list failure/retry paths remain usable. Release requires verification of that journey and of the hidden controls in empty and populated Messages views, including keyboard access.

## Risks

- **Reduced discoverability:** Users may not know where to start a conversation. **Early validation:** Confirm the existing user-page composer and normal navigation to recipient pages before Step 3. **Mitigation:** Retain that route; the approved assessment accepts this tradeoff without adding guidance.
- **Stranded controls or regressions:** Hiding only the form could leave a dead button or disrupt the list. **Early validation:** Review both list states and their shared behavior before Step 3. **Mitigation:** Treat the form and empty-state trigger as one scope and require list/recovery regression checks.

## Shared component inventory

- **Messages list and conversation rows:** Adapt the existing list surface; reuse its rows, previews, unread indicators, paging, and recovery controls.
- **Username page, profile page, and conversation view:** Reuse the existing shared private-message composer and conversation reader; no new messaging component.
- **Messaging APIs:** Reuse existing recipient eligibility/keys, send, conversation/list, legacy inbox/sent, unread/read, and history-sync services unchanged; this is a presentation change.

## Simple user flow

1. Open the recipient’s user page through existing site navigation.
2. Send a private message using the existing composer; use its existing recovery controls if sending fails.
3. Open Messages, find the conversation, and continue replying there.

## Success criteria

- Zero exposed “New message” forms or triggers on empty or populated Messages pages, by mouse or keyboard.
- The user-page send → Messages list → conversation reply journey succeeds for an eligible sender and recipient.
- Existing list pagination/retry, send recovery, and access restrictions pass the relevant regression checks.
