# Private Message Reader Refinement: Step 2 Feature Description

> **Feature plan:** [Step 1](./private_message_reader_refinement_step1_solution_assessment.md) · [Step 2](./private_message_reader_refinement_step2_feature_description.md) · [Step 3](./private_message_reader_refinement_step3_development_plan.md) · [Step 4](./private_message_reader_refinement_step4_implementation_summary.md)

## Problem

Inbox and sent-mail cards require a manual decrypt-and-verify action and expose reader diagnostics that interrupt normal reading. Automatic, bounded reads need to retain local cryptographic verification without persisting plaintext.

## User Stories

- As a recipient, I want Inbox messages to decrypt and verify automatically so that I can read them without a separate action.
- As a message reader, I want a successful signature verification shown beside the counterpart username so that I can assess sender trust in context.
- As a privacy-conscious member, I want plaintext to remain only in the active page session so that reading messages does not leave a persistent browser copy.

## Core Requirements

- Automatically decrypt and verify each message rendered in the authenticated Inbox and Sent views; remove the manual reader button and its status line.
- Show “Signature verified” after the From username for a verified Inbox message and after the To username for a verified Sent message.
- Hide the encrypted-message identifier module; do not remove or expose ciphertext in rendered HTML.
- Render at most 25 mailbox messages per page and automatically read only rendered messages.
- Do not store decrypted plaintext or verification results in `localStorage`, `sessionStorage`, or another persistent browser store.

## Delivery Scope

- Work type: application change.

## Completion Boundary

- Normal entry: an authenticated member opens Inbox or Sent.
- End-to-end outcome: up to 25 displayed messages are automatically decrypted and verified locally, their plaintext is shown, and successful verification appears beside the counterpart username.
- Recovery: an unavailable key, load failure, decryption failure, or invalid signature does not show plaintext or a verified label and provides a concise visible failure state without restoring the removed manual controls.
- Release condition: no persistent plaintext cache exists, ciphertext remains absent from server-rendered cards, and the encrypted-message module and manual reader UI are absent.

## Risks

- Impact: automatic reads can create excessive browser or API work on large mailboxes. Earliest validation: render a mailbox exceeding one page. Mitigation: enforce the 25-message page bound before automatic reads.
- Impact: a missing or invalid key could look like a successful read. Earliest validation: exercise unavailable-key, failed-decryption, and bad-signature fixtures. Mitigation: gate plaintext and the verified label strictly on successful verification.
- Impact: a cache could expose decrypted content after the page closes. Earliest validation: inspect browser storage after opening and revisiting a message. Mitigation: keep any reuse only in page memory.

## Shared Component Inventory

- Private-message mailbox page controller and mailbox service/API: extend the canonical Inbox/Sent retrieval surfaces with the bounded message list.
- `private_messages.php`: extend the canonical mailbox-card presentation for counterpart verification text and removal of the specified reader UI.
- `private_message_reader.js`: reuse the established local OpenPGP decrypt-and-verify path; change its invocation from button-driven to automatic.
- `private_messages.js` recipient-key lookup: reuse for sender-key resolution; no new key source is needed.

## Simple User Flow

1. An authenticated member opens Inbox or Sent.
2. The page renders no more than 25 authorized message cards and starts local reading for each.
3. Each successfully verified message shows plaintext and “Signature verified” beside its counterpart username.
4. A failed read withholds plaintext and the verified label while showing a concise recovery state.

## Success Criteria

- Inbox and Sent contain no decrypt-and-verify button, reader status line, or encrypted-message module.
- A verified Inbox message displays “Signature verified” beside From; a verified Sent message displays it beside To.
- A mailbox with more than 25 messages renders and automatically reads only 25 on one page.
- Browser persistent storage contains no decrypted plaintext or verification cache after reading messages.
- Invalid, unavailable, and unverifiable messages never display plaintext or “Signature verified.”
