# Private Message Conversations: Step 3 Development Plan

> **Feature plan:** [Step 1](./private_message_conversations_step1_solution_assessment.md) · [Step 2](./private_message_conversations_step2_feature_description.md) · [Step 3](./private_message_conversations_step3_development_plan.md) · [Step 4](./private_message_conversations_step4_implementation_summary.md)

## Completion Contract

- Normal entry: an approved member follows a counterpart link from Inbox or Sent.
- End-to-end outcome: the member sees the newest 25 authorized two-party messages in chronological order, locally verifies them, replies, and returns to the refreshed conversation.
- Required recovery: invalid, self, unavailable, or unauthorized counterparts reveal no conversation; failed verification withholds plaintext; failed sends retain the draft without submitting plaintext.
- Deployment/external verification: retain no-store responses; confirm private conversation routes, assets, envelopes, and plaintext remain absent from public/static/offline output. No migration or external-service change is required.
- Release condition: counterpart ownership, bounded ordering, all-approved-key reply coverage, recovery, focused tests, and release isolation pass.

## Key Risks

- **High risk: cross-conversation disclosure.** Early validation: three-user fixture with interleaved messages. Mitigation: one store/service counterpart query scoped to the approved viewer's username in both directions.
- **High risk: incomplete or misordered transcript.** Early validation: fixture exceeding 25 messages. Mitigation: define newest-25 selection and chronological presentation in the canonical store contract.
- **High risk: reply or reader drift.** Early validation: decrypt and reply from the conversation page. Mitigation: retain the shared composer, recipient-key API, and verified local reader.

## Stage 1 - Deliver an authorized conversation view

- Goal: Let an approved member open and read a bounded, locally verified conversation with one authorized counterpart.
- Dependencies: Approved Step 2; existing mailbox authorization, envelope reader, and private-store tests.
- Expected changes: Add conceptual `PrivateMessageStore::conversationFor(viewerUsernameToken, counterpartUsernameToken): array` and `PrivateMessageMailboxService::conversation(viewer, counterpartUsernameToken): array` contracts for newest-25 two-way retrieval; add authenticated no-store conversation page/API routes; render ordered conversation cards and link Inbox/Sent counterparts to them; extend the existing reader to retrieve and locally verify this canonical list.
- Verification approach: Exercise eligible two-way, third-party, self, invalid, and pending-only counterpart fixtures; assert newest-25 selection then chronological display, no ciphertext in HTML, verified-only plaintext, no-store responses, and no persistent plaintext.
- Risks or open questions:
  - Impact: A wrong direction filter reveals unrelated mail.
  - Early warning / validation: A third user's message appears in the fixture transcript.
  - Mitigation: Test the identity-scoped store result before controller and browser rendering.
- Canonical components/API contracts touched: `PrivateMessageStore`, `PrivateMessageMailboxService`, `PrivateMessageApiController`, `PrivateMessagePageController`, `/messages/conversation/{username}`, counterpart mailbox links, conversation presentation, and `private_message_reader.js`.

## Stage 2 - Reply from the conversation context

- Goal: Let an approved member send an encrypted reply and return to its refreshed conversation.
- Dependencies: Stage 1.
- Expected changes: Place the shared private-message composer on eligible conversation pages with the counterpart token; add an optional successful-send refresh target to the existing composer while preserving profile-page behavior; extend release-isolation coverage for conversation routes and assets.
- Verification approach: Send from a conversation fixture and confirm the request has no plaintext, every approved recipient key can decrypt, success refreshes the transcript, and a failure preserves the counterpart-scoped draft; rerun reader, composer, mailbox, API, and static/offline isolation checks.
- Risks or open questions:
  - Impact: A successful reply may not appear, or a failed one may lose content.
  - Early warning / validation: The conversation does not refresh after success or the draft disappears after a forced failure.
  - Mitigation: Reuse the current stable draft/message-ID lifecycle and make refresh opt-in only for the conversation page.
- Canonical components/API contracts touched: shared private-message composer partial, `private_message_compose.js`, `private_messages.js`, conversation page/controller, `ApprovedUserKeyResolver`, `/api/private_messages`, and release-isolation tests.
