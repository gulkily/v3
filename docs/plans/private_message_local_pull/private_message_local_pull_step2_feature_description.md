> **Feature plan:** [Step 1](./private_message_local_pull_step1_solution_assessment.md) · [Step 2](./private_message_local_pull_step2_feature_description.md) · [Step 3](./private_message_local_pull_step3_development_plan.md) · [Step 4](./private_message_local_pull_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/private_message_local_pull`; [cycle ledger](../effort_ledger/private_message_local_pull.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.95**

## Problem

A member cannot read their zenmemes.com private messages in a local clone. Private storage never leaves the server, and no path imports it. The local browser key also never reaches zenmemes.com, so zenmemes.com cannot approve it or address history to it.

## User Stories

- As a member running a local clone, I want to register my local key on zenmemes.com from the local page so that I can approve it there like any second key.
- As that member, I want to pull my own messages from zenmemes.com so that I can read my conversations locally.
- As that member, I want a second pull to fetch only new messages so that I can pull again whenever I like.
- As that member, I want my private key to stay in my local browser so that registering and pulling never copy a secret.
- As another member of zenmemes.com, I want only the requesting member's mail to leave the server so that my other conversations stay private.

## Core Requirements

- **Register the key.** The local page registers the local public key on zenmemes.com under the member's username, using the existing two-step identity registration. A key that already exists counts as registered. Approval stays a separate, manual step on zenmemes.com.
- **Own mail only, original identity.** zenmemes.com exports only the signed-in member's messages, plus transfers addressed to that member's keys. The clone stores each envelope and transfer under its original ID, without changing ciphertext. The pull never sends a message.
- **Confirm what reads.** After a transfer checks out locally, the pull confirms it on zenmemes.com, just as an ordinary target device does. Unconfirmed transfers stay replaceable by later donors.
- **Safe to repeat, key stays put.** Repeat or interrupted pulls neither duplicate nor lose anything. A local message with the same ID but different contents keeps the local copy, and the pull reports the mismatch. The local browser signs every request. The local server relays it and keeps the zenmemes.com session only for that pull.
- **Read state follows once.** Pulled messages arrive read or unread, matching zenmemes.com at pull time. Reads on localhost do not flow back.

## Delivery Scope

Work type: application change. zenmemes.com gets a new member export endpoint. The local clone gets the key registration step, the pull and the confirmation step. Includes this cycle's effort-ledger entry and the concept updates below. Out of scope: scheduling, a CLI pull, sending messages back, other instances as sources, operator-wide backups and automatic approval.

## Completion Boundary

- **Normal entry:** the local development page from `./v3 start`, after the public import.
- **End-to-end outcome:** the page registers the key, then waits for approval. Pressing **Pull my messages from zenmemes.com** then fills local Messages. Each message shows verified text, or shows "not readable yet" when no transfer covers it.
- **Recovery:** each failure names its cause and next step. Failures include an unregistered or unapproved key, an approval the clone has not imported, an unreachable or outdated zenmemes.com, an expired sign-in, an unconfirmed transfer and an interrupted pull. Pressing the button again resumes.
- **Release condition:** the export endpoint is deployed to zenmemes.com. Ilya registers a key, approves it, pulls, and pulls again from a laptop clone.

## Known User and Environment

Ilya, on a laptop clone started with `./v3 start`, pulling from zenmemes.com.

- **Mismatch:** the local key is unregistered, unapproved, or approved but not yet imported. The page says which. Ilya then presses Register, approves the key on zenmemes.com (Tools → User Access), or reruns the public import.
- **Not readable yet:** messages pull, but older ones stay unreadable. The page suggests visiting zenmemes.com on an existing device, then pulling again.
- **Verify:** the local Messages count matches zenmemes.com, and a second pull reports nothing new. On zenmemes.com, the transfers to the local key show as confirmed. Evidence comes from that laptop against production, not only from a test fixture.

## Risks

- **Sign-in relay.** The local server holds a zenmemes.com session for one pull. A leaked session exposes ciphertext and metadata only, never a key. Validate early: relay registration and sign-in from loopback to a test instance. Mitigation: keep the session in memory and drop it after the pull.
- **Transfers fail locally.** Transfers bind to account, keys and envelope, and local profiles might disagree, for example over a retired key. Validate early: register, approve and pull a two-device test account into a fresh clone. Mitigation: confirm only verified transfers, and report the rest.
- **Wrong username.** A key registered under the wrong name joins no account. Mitigation: the page shows the username before registering, and stops when zenmemes.com has no approved key with that name.
- **Export load and deploy order.** Long histories could strain zenmemes.com, and older deployments lack the endpoint. Mitigation: bounded, resumable batches within history sync's existing limits. The pull detects a missing endpoint, says so, and changes nothing locally.

## Shared Component Inventory

- **Local development page** (`LocalDevelopmentPageController`, `LocalDevelopmentImport`, `templates/pages/local_development.php`, `public/assets/local_development.js`): extend with registration and the pull. Reuse its loopback guard, token and resume pattern.
- **Identity registration** (`prepare_identity`, `create_identity` in `WritePostAndIdentityApiController`): reuse unchanged on zenmemes.com. zenmemes.com sends no CORS headers, so the local server relays.
- **Sign-in** (`AuthApiController` challenge and authenticate, browser signing in `private_site_auth.js`): reuse unchanged through the same relay.
- **Mailbox reads** (`PrivateMessageApiController`, `PrivateMessageMailboxService`): extend with a member export endpoint. Inbox and sent return no transfers and no resumable position, and fetch transfers one message at a time.
- **Transfers and confirmation** (`PrivateMessageHistorySyncStore`, `PrivateMessageHistorySyncController` acknowledge): reuse unchanged. Transfer IDs are content hashes, so imports keep their identity.
- **Envelope storage** (`PrivateMessageStore::storeEnvelope`): reuse; it already accepts a caller-chosen message ID.
- **Read state** (`PrivateMessageReadState`, unread endpoint): reuse to copy state at pull time.
- **Route catalog** (`ApiRouteCatalog`): register the export route.
- **Messages pages, public import** (`scripts/import_instance.php`), **approval** (Tools → User Access): unchanged.

## Concepts Touched

- [Local development import](../../concepts/local_development_import.md): changed, gains key registration and the messages pull.
- [Private-message history](../../concepts/private_message_history.md): used unchanged; note that a pulled key confirms through the relay.
- [Approval acceptance](../../concepts/approval_acceptance.md): used unchanged.
- Proposed *participant mailbox export*: created.

## User Flow

1. On the local development page, Ilya checks the username and presses **Register this key on zenmemes.com**. The local browser signs; the local server relays.
2. The page links to the new key's page on zenmemes.com. Ilya approves it there from an existing device.
3. On localhost, he reruns the public import, so the clone learns the approval.
4. He visits zenmemes.com once on an existing device, and history sync covers the new key.
5. He presses **Pull my messages from zenmemes.com** and signs the relayed challenge. The page shows progress, then a summary: new, skipped, mismatched, confirmed and not readable yet.
6. He opens local Messages and reads his conversations.

## Success Criteria

- Registering twice leaves one identity on zenmemes.com and reports success both times.
- A pull into a fresh clone shows every message that the same key can read on zenmemes.com, each with a verified sender.
- After that pull, zenmemes.com records each verified transfer as confirmed, and no unverified one.
- A second pull with no new mail adds nothing and reports zero new. An interrupted pull, resumed, ends with the same result.
- The export never returns a message or transfer outside the signed-in account; tests cover another member's mail.
- No private key or plaintext reaches either server.

*— Claude · 2026-11-18 UTC · Model: claude-opus-5-5*
*— Revised by Claude · 2026-11-18 UTC · Model: claude-opus-5-5 (added key registration and transfer confirmation at the maintainer's request)*

## Enhancement Requests

- Record the participant mailbox export and the original-identity import in the "APIs that should exist" list. Disposition: in scope; this cycle builds both.
- Register the local key on zenmemes.com, and confirm verified transfers there (maintainer, 2026-11-18T03:32Z: "Yes, please revise with both"). Disposition: in scope, added in this revision.

## Study Checkpoint

2026-11-18T02:55Z; scope v1; starting revision `6bdc52d7`; reference R1 (hashes not re-verified this session); P2 standing enrollment, no parent. Claude, single exposed rater.

- Step 2 handoff: **0.85 projected total**, up from 0.80 at Step 1. Reason: new understanding, not changed scope. Carrying read state and handling unreadable messages add work. Not hours or measured labor; no implementation, accepted outcome or approval to advance.

Revision, 2026-11-18T03:35Z; scope v2.

- Step 2 revised handoff: **0.95 projected total**. Reason: changed scope. Key registration and transfer confirmation were missing from v1, and the maintainer added them. Both reuse existing endpoints through the same relay. Same rater and exposure.
- Ledger update pending: the ledger entry is in delivery scope and lands with the planning commit.

Status: Step 1 approved by the maintainer ("approved step 1", 2026-11-18T02:51Z). Step 2 v2 awaits approval.
