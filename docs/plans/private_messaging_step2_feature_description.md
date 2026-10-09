# Private Messaging Step 2 Feature Description

> **Feature plan:** [Step 1](./private_messaging_step1_solution_assessment.md) · [Step 2](./private_messaging_step2_feature_description.md) · [Step 3](./private_messaging_step3_development_plan.md) · [Step 4](./private_messaging_step4_implementation_summary.md)

## Problem

Members can identify one another and hold OpenPGP keys but cannot communicate privately without taking the conversation off-site. This first cycle adds a small, encrypted identity-to-identity mailbox while keeping messages out of public forum data.

## User Stories

- As a member, I want to start a private message from another member's profile so that I can contact them directly.
- As a recipient, I want to see and decrypt messages addressed to me so that I can read them in the forum.
- As a sender, I want a readable sent copy so that I can confirm what I sent.

## Core Requirements

- Browser-side OpenPGP encryption and signing; encrypt each message for every active recipient and sender key.
- Store only encrypted message material in private runtime state, never the public repository, public read model, static artifacts, or offline snapshot.
- Require the existing key-possession session authentication for send, inbox, and sent-mail access.
- Deliver profile-to-member, identity-to-identity text messages with inbox, sent-mail, and local decryption; include every active key held by each party.
- Make the privacy limit clear: content is private from public readers, while the operator retains ciphertext and delivery metadata.

## Delivery Scope

- Work type: application change.
- This FDP cycle excludes group messages, attachments, notifications, search, read receipts, editing/deleting, blocking, offline sending, and key-management UI. Those are candidates for later cycles.

## Completion Boundary

- Normal entry: an authenticated member opens another member's profile and chooses to message them.
- End-to-end outcome: the sender sends an encrypted text message, sees it in Sent, and the recipient authenticates, finds it in Inbox, and decrypts it locally.
- Recovery: failed encryption, authentication, or delivery leaves the draft unsent and shows an actionable error; encrypted stored content is never silently replaced with plaintext.
- Release condition: recipient and sender access are identity-scoped, and public repository/read-model/static/offline outputs contain no message body or encrypted envelope.

## Risks

- Multi-key recipient handling could omit a device; validate with identities having multiple active keys before planning, and define authoritative active-key discovery.
- Key changes could make older messages unreadable; validate old-key and new-key reads early, then state the first-cycle key-retention behavior.
- A private-store route could accidentally enter public outputs; validate with a repository/read-model/static/offline inspection and isolate the data path.

## Shared Component Inventory

- Profile page, Users directory, and Forte user detail: extend the canonical recipient profile presentation with the message entry point.
- `/api/get_profile` and profile public-key data: reuse as the source for recipient identity/key discovery; extend only if the active-key set is not already exposed.
- Browser signing/OpenPGP runtime and challenge-signature authentication: reuse for encryption/signing and mailbox authorization.
- Composer, Inbox/Sent list, message reader, and mailbox APIs: new; no equivalent private-message surface exists.

## Simple User Flow

1. An authenticated member opens a recipient's profile and selects Message.
2. The browser obtains the active keys, encrypts and signs the text, then sends the envelope.
3. The sender sees the message in Sent.
4. The recipient opens Inbox; their browser decrypts and displays the message.

## Success Criteria

- A sender and recipient with multiple active keys can complete the flow and read their respective copy.
- An unrelated authenticated member cannot list or retrieve either mailbox.
- Public Git, SQLite read-model, static release, and offline snapshot checks contain no private-message payload.
- A recoverable send failure preserves the local draft and gives the user a next action.
