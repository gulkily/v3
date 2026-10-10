# Compact unavailable private messages Step 2 feature description

> **Feature plan:** [Step 1](./private_message_unavailable_step1_solution_assessment.md) · [Step 2](./private_message_unavailable_step2_feature_description.md) · [Step 3](./private_message_unavailable_step3_development_plan.md) · [Step 4](./private_message_unavailable_step4_implementation_summary.md)

## Problem

Repeated decryption errors and retry buttons overwhelm conversation history. Combine compact placeholders with expandable groups so unreadable stretches remain understandable without obscuring readable messages or security warnings.

## User stories

- As a reader, I want unavailable stretches summarized so that readable messages remain easy to find.
- As a reader, I want individual timestamps, explanations, and retry available so that I can inspect or recover a message without losing my place.
- As a reader, I want signature warnings to remain visible so that compact presentation never implies trust.

## Core requirements

- Show an isolated unavailable message as a compact placeholder. Collapse runs of two or more settled unavailable messages from the same sender and local date into a count, sender/direction, and time-range summary. Readable messages, loading states/failures, security warnings, sender changes, and date separators break runs.
- Expanding a group reveals compact individual placeholders with exact timestamps and optional details/retry; collapsing restores its summary. Controls expose accessible names and expanded states. Preserve reading position, focus, and existing expanded choices during older-history loading, retries, and sends, including groups joining across loaded-page boundaries.
- Keep known loading/support failures visible with direct retry; keep missing, invalid, or unverifiable-signature warnings prominent and ungrouped. Missing private keys and settled decryption failures may be compacted. Unknown failures stay visible with neutral wording. Never infer key mismatch from a generic failure or show unverified plaintext.
- Retry only on request, show progress, and keep failures recoverable without repeated automatic attempts. A verified recovery appears in chronological position and splits or updates its group without being hidden; a failed retry retains access to its explanation. Explain retry's limits without promising historical-key recovery.
- Preserve drafts, sending, and unread semantics: after reading attempts settle, the visible summary containing the latest message can represent that message for acknowledgment, subject to existing focus/visibility gates and the original received boundary. Expanding/collapsing cannot broaden that boundary; later arrivals remain unread. Preserve private-data storage and static/offline exclusions.

## Delivery scope and completion boundary

Application change, following approved Step 1. Deliver Messages → conversation → grouped unavailable history → expand/details/retry → readable recovery or honest unavailable state, including older history and reply continuity. Release only after normal-flow browser and focused regression checks pass. Exclude key management, re-encryption, schema changes, live delivery, and new unread semantics; fit within one day or eight stages, otherwise rescope before implementation.

## Risks

- **Misclassified failures hide recovery or warnings.** Early inspection confirms the reader currently combines several failures. Before Step 3, adopt the conservative categories above; unknown cases remain visible, and planning must test loading/support, decryption, and signature outcomes separately.
- **Regrouping disrupts focus or chronology.** Earliest validation: mixed-result, cross-page, and successful-retry scenarios. Mitigation: preserve expanded choices when groups join, retain access to focused controls, and restore recovered messages in order; make these blocking browser checks.
- **Collapsed latest messages stall or advance seen state incorrectly.** Early inspection confirms acknowledgment depends on latest-message visibility. Mitigation: recognize its visible summary without changing the authorized boundary; require hidden-tab, later-arrival, and expansion checks before release.

## Shared component inventory

- Conversation initial history, Load older/restart, and inline sent messages: extend the canonical message item, reader, conversation layout, and time formatting; grouping belongs in that shared transcript flow, not a second reader.
- Messages list and Inbox/Sent entry points: reuse shared reading outcomes for honest previews and retry; no transcript grouping in list rows.
- Navigation/list unread indicators and conversation acknowledgment: reuse existing state and receipt contracts, adapting only presentation visibility.
- Conversation, profile, and aggregate-user composers: retain shared draft/send behavior. Conversation/list/legacy mailbox, key-lookup, send, and unread APIs remain compatible; no new endpoint is needed.

## User flow

1. Open a conversation from Messages and read past compact unavailable summaries.
2. Expand a summary, inspect an individual message, and optionally retry.
3. Continue reading older history or replying without losing position, draft, or warning visibility.

## Success criteria

Twenty consecutive eligible messages initially show one summary and expand to exactly twenty ordered placeholders. Mixed sender/date/security/loading cases never group incorrectly. Retry recovery, cross-page grouping, preserved focus/drafts, and unchanged acknowledgment boundaries pass desktop, 375px mobile, and keyboard browser checks; no unverified plaintext or horizontal overflow appears.
