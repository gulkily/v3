# Private Message Conversations: Step 2 Feature Description

> **Feature plan:** [Step 1](./private_message_conversations_step1_solution_assessment.md) · [Step 2](./private_message_conversations_step2_feature_description.md) · [Step 3](./private_message_conversations_step3_development_plan.md) · [Step 4](./private_message_conversations_step4_implementation_summary.md)

## Problem

Inbox and Sent divide a two-party exchange into separate lists, preventing members from reading the exchange or replying in context. A counterpart-scoped view must preserve the existing private-message authorization and browser encryption boundaries.

## User Stories

- As an approved member, I want to open a conversation with a message counterpart so that I can read our recent exchange in one ordered view.
- As an approved member, I want to reply from that conversation so that the response stays in context and reaches every approved recipient key.
- As a privacy-conscious member, I want conversation plaintext verified and kept only in the active page so that the view does not weaken private-message handling.

## Core Requirements

- Provide an authenticated, counterpart-scoped conversation view entered from Inbox and Sent.
- Show the newest 25 messages between the viewer's and counterpart's composite usernames, in chronological order, from both directions only.
- Include the established private-message composer pre-targeted to the counterpart; retain browser-side encryption, signing, recipient-key coverage, and draft recovery.
- Decrypt and verify conversation messages locally; never render ciphertext or persist plaintext or verification state.
- Deny unavailable, self, unapproved, or unrelated counterpart access without disclosing correspondence.

## Delivery Scope

- Work type: application change.
- This cycle excludes group conversations, pagination/history beyond the newest 25 messages, attachments, notifications, search, read receipts, editing, deletion, and key-management changes.

## Completion Boundary

- Normal entry: an approved member follows a counterpart link from Inbox or Sent.
- End-to-end outcome: the member reads the recent authorized exchange, sends a verified encrypted reply, and sees it in the refreshed conversation.
- Recovery: unavailable keys, failed verification, encryption, or delivery withhold plaintext as applicable, retain the draft on failed send, and provide the existing actionable feedback.
- Release condition: conversation queries are identity-scoped, each reply covers every approved recipient key, and no private content enters public, static, or offline output.

## Risks

- **Cross-conversation disclosure** could expose a third party's correspondence. Earliest validation: fixture with three users and mixed messages. Mitigation: scope both-direction retrieval to the authenticated username and selected counterpart.
- **Incomplete or misordered history** could misrepresent an exchange. Earliest validation: interleaved fixture beyond the 25-message bound. Mitigation: define one newest-25 ordering contract before planning.
- **Reply or reader drift** could weaken key coverage or verification. Earliest validation: send and decrypt from the conversation fixture. Mitigation: reuse the existing composer, recipient-key source, and local reader.

## Shared Component Inventory

- Inbox/Sent page and mailbox cards: extend as the canonical conversation entry points.
- Private-message store, mailbox service, and authenticated no-store APIs: extend with one counterpart-scoped retrieval contract.
- Private-message composer partial and browser helper: reuse for the reply form, encryption, signing, submission, and draft recovery.
- Private-message reader and mailbox presentation: extend for ordered conversation cards and local verification; no new plaintext source is needed.

## Simple User Flow

1. An approved member opens Inbox or Sent and selects a counterpart.
2. The conversation view loads the newest authorized messages in both directions and verifies them locally.
3. The member writes and sends a reply from the same view.
4. The conversation refreshes with the new encrypted reply or retains the draft with recovery feedback.

## Success Criteria

- A member can open a counterpart conversation from Inbox and Sent; unrelated or ineligible users receive no conversation data.
- The view contains at most 25 correctly ordered messages from only the two composite usernames.
- A reply is encrypted to every approved recipient key, appears after refresh, and failed sends preserve the draft without submitting plaintext.
- Verified messages alone display plaintext; public/static/offline outputs contain neither conversation content nor private-message assets.
