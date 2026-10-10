# Private Messaging Architecture and Features

Private messaging provides encrypted, signed text conversations between approved forum users. The browser encrypts and signs messages before sending them, and decrypts and verifies them when reading. The server authenticates users, delivers encrypted envelopes, and maintains private conversation and unread metadata in a dedicated SQLite database.

The implementation includes a unified Messages list, recipient discovery, conversation replies without a page reload, recoverable drafts and sends, paginated history, unread conversation counts, and compact presentation of unavailable messages. This overview describes the current repository implementation; deployment status depends on the individual site.

## Architectural choices

| Choice | Implementation and reason |
| --- | --- |
| Private SQLite storage | Messages are authoritative private runtime state, separate from public Git records and the derived public read model. This avoids publishing ciphertext and avoids the repository, locking, and retention complexity of a separate private Git repository. |
| Browser encryption and signing | Existing OpenPGP browser identities provide encryption, signing, decryption, and verification. The normal send path submits ciphertext rather than message text or private keys. |
| Delivery by composite username | A user is the set of approved profiles sharing a normalized username. Encryption covers the approved keys of both participants, supporting multiple identities and a readable sender copy. |
| Existing session authentication | The server reuses authentication based on proof of private-key possession. It derives the sender and mailbox owner from the approved session profile. |
| Verification before display | Both message bodies and list previews use the same browser verification path. Unverified plaintext is withheld. |
| Bounded snapshot pagination | Lists and history load 25 items at a time, with insertion boundaries and deterministic ordering so later arrivals do not shift an ongoing traversal. |
| Durable unread positions | Private metadata records progress per username and counterpart. Server-authenticated receipts limit acknowledgments to a captured received-message boundary. |
| Shared presentation and recovery | List, conversation, profile, and aggregate-user entry points reuse the composer, reader, message templates, and time formatter. |

The original [architecture assessment](../plans/private_messaging_step1_solution_assessment.md) compares the storage alternatives. The [profile read contract](../specs/profile_read_contract_v1.md) defines composite users.

## Identity and key coverage

A normalized username token identifies a mailbox and a conversation participant. Multiple approved profiles can belong to that username, each with its own OpenPGP identity. Pending profiles and profiles without public keys are excluded from the recipient key set; duplicate public keys are removed.

When preparing a message, the browser obtains the current approved public keys for both username groups. It encrypts one OpenPGP envelope to their combined key set, also including the local sender public key, and signs with the matching local private key. OpenPGP handles the per-message content key and its encryption for the recipients; users do not choose a separate message password. A single stored envelope supplies both the incoming and sent views.

Mailbox authorization and unread state are shared across approved identities with the same username. Decryption still requires a private key covered by that particular envelope. Approving a new profile key does not retroactively grant it access to old ciphertext, and losing an old private key can make earlier messages unreadable. Verification uses the sender group's currently approved public keys, so changes in that set can also affect verification of historical messages.

Key resolution is implemented in [ApprovedUserKeyResolver](../../src/ForumRewrite/Messaging/ApprovedUserKeyResolver.php); browser encryption is in [private_messages.js](../../public/assets/private_messages.js).

## Sending and reading a message

1. An approved authenticated member opens a conversation through Messages, a profile, or the aggregate user page.
2. The composer checks the browser identity, retrieves participant keys, and prepares a signed encrypted envelope locally.
3. The browser submits a message ID, recipient username, and armored envelope. The service derives the sender username and identity from the session and assigns the acceptance timestamp.
4. The private store retains the envelope and routing metadata. The response confirms the accepted message ID, participants, and timestamp.
5. The other participant opens the conversation. Server-rendered HTML supplies metadata and message placeholders; authenticated API requests retrieve the envelopes for that window.
6. The reader decrypts with the saved browser private key and verifies signatures against the sender group's approved keys. It requires at least one signature and waits for all returned signature verification promises to succeed before rendering text.

Verified text is inserted as text content, not interpreted as HTML. Decryption or verification failures affect the individual message; other messages can still be read. List previews use this same verification rule and remain navigable when a preview cannot be displayed.

The server validates routing fields, approved-user eligibility, and basic armored-envelope shape and size, with a maximum envelope size of 1 MiB. It does not decrypt or cryptographically validate message contents on submission; that verification happens in the browser when reading. See the [mailbox service](../../src/ForumRewrite/Messaging/PrivateMessageMailboxService.php) and [reader](../../public/assets/private_message_reader.js).

## Implemented user features

### Finding and starting conversations

`/messages` is the canonical entry point. It shows one row per counterpart, ordered by latest activity across both sent and received messages, with a locally verified one-line preview and a local timestamp. Aggregation happens before pagination, so a busy conversation cannot hide other counterparts behind the old mailbox limit. Load more exposes further conversations with loading, retry, and end-of-list states.

New message supports username suggestions from the approved user directory and direct username entry. Invalid, self, or unavailable recipients receive feedback without losing the entered username. Empty mailboxes expose the same entry point. Conversations link back to Messages and to the counterpart's user page. The original `/messages/inbox` and `/messages/sent` pages redirect to Messages; their APIs remain available for compatibility.

### Reading and replying

The conversation view uses compact directional messages, consecutive-sender grouping, and local-date separators. Friendly local timestamps retain exact values for hover and accessible descriptions. Verified signatures have a quiet indicator; missing or invalid signatures remain visible warnings.

The composer grows with its contents. Ctrl/Cmd+Enter sends, while Enter and Shift+Enter insert newlines; input-method composition is respected. A confirmed send appends the message in place using the server-confirmed identity and timestamp. The view supports returning to the latest message and preserves the reading position when loading earlier history. Opening the conversation does not automatically focus the composer and summon a mobile keyboard.

### Drafts and uncertain delivery

Drafts are saved in browser `localStorage`, scoped to the sender and recipient username, with a check against the saved sending key. **Draft text is stored locally as plaintext.** An outstanding send also retains its message ID and exact encrypted envelope so it can be checked again after an uncertain network result.

The server accepts a repeated message ID only when the authenticated sender identity, sender username, recipient, and envelope match the original attempt. An exact retry returns the original acknowledgment; a conflicting reuse is rejected. This prevents the supported retry flow from creating duplicate messages or overwriting an accepted envelope.

A successful response clears the submitted draft only if it has not been edited in the meantime. Newer text survives, and an unresolved earlier send must be checked before an edited draft is sent. If browser storage is unavailable, the composer explains that recovery cannot be saved and asks the user to keep the page open. See the [composer](../../public/assets/private_message_compose.js) and [store](../../src/ForumRewrite/Messaging/PrivateMessageStore.php).

### Loading older history

Conversations initially show the latest 25 messages in chronological order. Load older retrieves earlier pages within the opening snapshot and prepends them while preserving the visible message and its position, including after asynchronous decryption changes the layout.

Conversation and list pagination use insertion boundaries, timestamp ordering, and insertion order to break timestamp ties. Cursors are scoped to the viewer and, for history, the counterpart. Page replay keeps initially rendered metadata and later envelope retrieval on the same snapshot. New arrivals, including backdated ones, do not alter that traversal.

Failed loads can retry the same cursor. Invalid or expired history positions offer an explicit restart; the existing transcript is replaced only after a valid fresh response, and drafts and concurrent send confirmations are retained. Refreshing or restarting opens a new snapshot. Incoming messages are not delivered through live polling or push.

### Unread conversations

The navigation badge counts **conversations**, not individual messages. Each list row can show whether that counterpart has received messages beyond the viewer's saved position. The server calculates this from private metadata without fetching or decrypting bodies.

Automatic acknowledgment occurs after the current conversation window's reading attempts have settled, the document is visible and focused, and the latest message or its compact group is visible. It acknowledges received history through the window's captured boundary, including displayed failures. Consequently, seen means that the conversation window was presented; it does not prove that every message was individually viewed, decrypted, verified, or understood. No sender-facing read receipts are provided.

The server issues an HMAC-protected receipt binding the viewer, counterpart, and received insertion boundary. Acknowledgments require that receipt and a same-origin JSON request. Pagination cursors do not authorize read-state changes. Progress only moves forward, and retrying an acknowledgment uses the same receipt so it cannot accidentally include later arrivals.

List previews, loading older pages, and sending a reply do not broaden that boundary. Unread progress is shared across the username's identities and devices, with refresh on navigation and visible return rather than polling. Failed status retrieval appears as unavailable, not as a zero count.

On first initialization of unread tracking, existing history is treated as seen once. Subsequent initialization preserves that baseline and recorded progress. See [PrivateMessageReadState](../../src/ForumRewrite/Messaging/PrivateMessageReadState.php) and the [unread rollout notes](../plans/private_message_unread/private_message_unread_rollout.md).

### Unavailable messages and recovery

Missing-key and decryption failures use compact placeholders with expandable details and manual retry. Consecutive settled unavailable messages from the same sender and local date collapse into a count/time summary. Individual message identities and ordering remain intact, and expanded choices survive history-page joins.

Readable messages, signature warnings, pending reads, support-loading failures, and unreadable saved-key errors are kept distinct from these collapsed runs. A successful verified retry restores the message and splits its surrounding group. A generic decryption error does not establish that a particular key mismatch caused it, and repeating an unchanged attempt cannot repair missing key coverage.

Grouping is a browser presentation feature: it adds no envelope format, schema, or API change. A visible group can represent its latest message for the existing acknowledgment rule. See the [compact-message rollout notes](../plans/private_message_unavailable/private_message_unavailable_rollout.md).

## Privacy and storage boundaries

| Data | Where it lives |
| --- | --- |
| Encrypted message body | The private SQLite mailbox and authenticated API responses; browser memory while reading or preparing sends. A pending encrypted send can also be retained in local draft storage. |
| Delivery metadata | Private SQLite: message ID, timestamp, sender and recipient usernames, and sending identity. Authorized pages and APIs expose the metadata they need. |
| Unread metadata | Private SQLite: initialization baseline, receipt-signing secret, and seen positions per viewer/counterpart. |
| Decrypted bodies and previews | Browser memory and DOM during the current view; the reader does not write them back to the server or into persistent preview caches. |
| Draft text | Browser local storage when available, plus the live composer. |
| Browser private key | The existing browser identity storage. The mailbox server does not retain a recovery copy. |

Private messages and their envelopes stay outside the public content repository, public SQLite read model, static releases, and offline snapshots. Private pages and APIs return no-store responses. Server-rendered mailbox HTML does not embed envelopes or decrypted bodies; the browser obtains envelopes through authenticated requests. These HTTP cache controls do not remove the deliberate local storage of identities and drafts.

The operator can see routing metadata, ciphertext size, and private unread state, and controls availability, backups, and retention. The normal application does not send the operator plaintext message bodies. However, the browser trusts the application's served JavaScript and approved-key directory; this is not protection against an operator who maliciously changes that code or key information, or against a compromised browser. Approval of all keys grouped under a username is therefore part of the trust model.

## Server components and API surface

The PHP application routes private messaging through [PrivateMessagePageController](../../src/ForumRewrite/Http/PrivateMessagePageController.php) and [PrivateMessageApiController](../../src/ForumRewrite/Http/PrivateMessageApiController.php). The mailbox service enforces approved-viewer and recipient eligibility; the store scopes queries to the authenticated username. Another approved user cannot use a client-supplied sender field or cursor to access someone else's mailbox.

| Endpoint | Purpose |
| --- | --- |
| `POST /api/private_messages` | Accept an encrypted envelope or confirm an exact repeated attempt. |
| `GET /api/private_messages/recipient_keys?username_token=...` | Resolve the username's approved public keys. |
| `GET /api/private_messages/conversations` | Retrieve a page of counterparts and their latest envelopes; accepts an optional cursor. |
| `GET /api/private_messages/conversation?username_token=...` | Retrieve a conversation window or history page; accepts an optional cursor. A fresh window also supplies a read receipt. |
| `GET /api/private_messages/unread` | Retrieve the global unread conversation count and optionally the states of up to 25 requested counterparts. |
| `POST /api/private_messages/read` | Acknowledge the received boundary in a signed receipt. |
| `GET /api/private_messages/inbox` and `GET /api/private_messages/sent` | Preserve the legacy mailbox APIs, each bounded to the latest 25 messages. |

All of these APIs require an approved authenticated viewer. The application exposes additional request, response, cursor, and recovery details at `/api/`, generated by [ApiTextController](../../src/ForumRewrite/Http/ApiTextController.php).

## Deployment and recovery

The mailbox defaults to `<application-root>/state/private/messages.sqlite3`. Operators can override it with `PRIVATE_MESSAGE_DATABASE_PATH` in private configuration. The database contains three tables: `private_messages`, `private_message_tracking`, and `private_message_seen`.

This database is authoritative: it cannot be rebuilt from public Git records. Keep it and its SQLite sidecars outside the document root, public repository, release artifacts, and offline snapshot tree, with restrictive filesystem permissions. Use SQLite's consistent backup mechanism and test restoration. A mailbox backup restores encrypted messages and tracking state, but cannot replace a user's lost private key.

Messages, tracking metadata, and their insertion anchors must be preserved together during backup, restore, and rollback. Manual deletion or database rewrites can invalidate baseline or seen anchors; the implementation reports unavailable state rather than silently resetting progress. There is no automated metadata repair or automatic message deletion. Retention maintenance must account for these relationships.

Deploy matching server code, templates, and fingerprinted browser assets together. Preserve private tracking tables and their signing secret during code rollback. The [production runbook](../runbooks/production_deploy.md#private-message-mailbox-operations) covers permissions and backup/restore procedures; the [unread rollout notes](../plans/private_message_unread/private_message_unread_rollout.md) cover initialization and metadata integrity.

## Current limits

- Text conversations between two composite users only; no group chats or attachments.
- No live incoming-message transport, email notifications, or push notifications.
- No message search, editing, deletion by users, blocking, or sender-facing read receipts.
- No offline mailbox reading or offline send queue. Local draft recovery is available independently of those features.
- No messaging-specific key-management interface, automatic historical re-encryption, or recovery of lost private keys. Historical-key recovery remains deferred.
- No ratcheting or forward-secrecy mechanism is implemented by the messaging layer; access to stored envelopes continues to depend on their original OpenPGP recipient keys.

## Verification and implementation records

Automated coverage includes real OpenPGP encryption and signature rejection, approved-key coverage, authorization and third-party isolation, exact send retry, draft preservation, snapshot pagination, unread races, identity changes, and exclusion from public/static/offline outputs. An isolated browser journey exercises the normal Messages-to-conversation flow, sending, older history, recovery, compact groups, and two authenticated browser contexts.

The latest compact-message implementation record reports **62 focused tests passing** and the encrypted Chromium browser journey passing. Its documented manual follow-ups are physical mobile keyboards, assistive-technology announcements, and other browser engines. Those recorded results are implementation evidence, not a production deployment certification.

- [Core encrypted messaging implementation](../plans/private_messaging_step4_implementation_summary.md)
- [Conversation list implementation](../plans/private_message_conversation_list/private_message_conversation_list_step4_implementation_summary.md)
- [Chat refinement implementation](../plans/private_message_chat_refinement/private_message_chat_refinement_step4_implementation_summary.md)
- [Older history implementation](../plans/private_message_history_step4_implementation_summary.md)
- [Unread state implementation](../plans/private_message_unread/private_message_unread_step4_implementation_summary.md)
- [Compact unavailable messages implementation](../plans/private_message_unavailable/private_message_unavailable_step4_implementation_summary.md)
- [Usability master checklist](../plans/private_messaging_usability_master_checklist.md)
- [Repeatable browser journey](../../tests/browser/private_message_list_browser.mjs)

The planning records retain historical stage and branch notes. Use this overview for the consolidated behavior and the linked records for the reasoning and verification behind individual changes.
