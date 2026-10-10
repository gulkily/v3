# Missing features to prioritize in v3

Prioritize the features that let an ordinary member **join, return, find context, and keep access**, together with the controls an organizer needs when trust breaks down. v3 already has distinctive infrastructure for portable records and verifiable participation. Its next gains should make those benefits usable every day.

This assessment reflects code and documentation at `b0bf4172`, reviewed on October 10, 2026. It is a repository-based product assessment, not a production audit or a measured retention study. The order assumes the [initial audience](v3_best_fit_audiences.md): existing clubs, local networks, research circles, and archives supported by a technical steward. A security-sensitive or high-volume deployment would require a different release bar.

## Recommended order

Two problems should be resolved before expanding the private-messaging promise: who controls a composite user's recipient keys, and how membership or a compromised key can be withdrawn. In parallel, build notifications as the first broad engagement feature.

| Priority | Feature | Main failure it addresses | Relative scope |
| --- | --- | --- | --- |
| Release gate for broader private messaging | Explicit account ownership and recipient-key control | Approving a same-name key changes future message recipients | Large |
| 1 | Personal notifications and unread state | A member receives a reply and never notices | Medium to large |
| 2 | General search across threads and replies | Preserved knowledge cannot be rediscovered | Medium |
| 3 | Guided key backup, device transfer, and recovery | Clearing storage or changing devices interrupts membership | Large |
| 4 | Membership revocation and a human moderation workflow | Organizers can admit people but lack a complete way to manage abuse or changed trust | Large; move to release gate for open growth |
| 5 | Member editing and intentional content removal | Corrections and withdrawals require operator work | Large |
| 6 | Complete private-message history and delivery feedback | Older messages disappear from the normal conversation view | Medium, after recipient-key decisions |
| 7 | A dependable everyday mobile and writing experience | Small friction compounds on every visit | Medium, delivered in narrow increments |
| 8 | Guided setup, backup, restore, and migration | The community remains dependent on a single technical steward | Medium to large |

These are comparative scope estimates, not delivery commitments. Confirm dependencies and inspect the affected paths before estimating engineering time.

## Release gate for private messaging and account ownership

**Current behavior.** Approved profiles sharing a normalized username form a composite user. `ApprovedUserKeyResolver` returns every approved key in that group. New private messages are encrypted to the union of sender and recipient group keys, and mailbox selection uses the username token. This is an explicit implementation contract. [Profile model](../specs/profile_read_contract_v1.md), [key resolver](../../src/ForumRewrite/Messaging/ApprovedUserKeyResolver.php), and [message store](../../src/ForumRewrite/Messaging/PrivateMessageStore.php)

**Why it matters.** An approval of another key with the same username affects confidentiality, not merely how the member directory looks. If a different person's key is approved into that group, newly composed messages include it among the encryption recipients. Previously encrypted messages are not automatically decryptable by a newly added key.

**Implement first.** Separate display names from account ownership. Define how an existing account authorizes another device key, how a name collision stays separate, and how loss of all keys is handled. Show the current recipient devices and make changes visible to correspondents. Provide a defined way to revoke a device or membership and stop including that key in future messages.

**Acceptance.** Approving an unrelated same-name identity cannot silently add it to someone's private-message recipients. Adding a legitimate device follows an explicit proof or recovery policy. Removing it prevents future delivery to that key. The interface explains whether a new device can read older messages.

This is a product and authorization design requirement. It should be decided before adding group messages or advertising private messaging to a wider audience.

## 1 Personal notifications and unread state

**Current behavior.** Activity pages and RSS exist. The app-version notification announces software changes. The inspected application routes and stores do not provide a personal reply/mention inbox, followed-thread notifications, or per-member read cursors. [Routes](../../src/ForumRewrite/Application.php) and [RSS](../../src/ForumRewrite/Http/RssFeed.php)

**Why first.** A community can survive a rough theme; it struggles when members cannot tell that someone answered them. This is the most direct obstacle to a second conversation after the first post.

**Smallest useful release.** Add an in-app inbox for replies and private-message arrival, follow/unfollow for threads, a last-read position, and a clear unread count. Then add an opt-in email digest or web push according to pilot preferences. Keep email addresses and push subscriptions in private operational storage, with opt-out and delivery deduplication. Private-message alerts should not require sending plaintext message bodies to the server.

**Acceptance.** A member who closes the site after posting can discover a later reply, open the exact contribution, and mark it read. Reading on another device updates the same state. Muted threads stay quiet, retried delivery does not duplicate alerts, and an unauthorized recipient receives no protected content.

As a design reference, Discourse exposes distinct watching, tracking, and muted states alongside email preferences. v3 can start with fewer choices while retaining member control. [Discourse notification documentation](https://meta.discourse.org/t/configuring-default-notification-settings-for-users/285619)

## 2 General search across threads and replies

**Current behavior.** QDB has a public substring search over root-post bodies. Other profiles have no equivalent general search page. An internal related-content service helps analysis find cross-thread matches; the SQL viewer serves technical exploration. These are useful pieces, but neither supplies ordinary members with complete forum search. [Search controller](../../src/ForumRewrite/Http/BoardPageController.php) and [related-content service](../../src/ForumRewrite/Analysis/RelatedContentSearchService.php)

**Smallest useful release.** Add one search field across supported presentations, indexing visible subjects and reply bodies. Include author, tag, and date filters, excerpts, relevance ordering, and a chronological alternative. Use a derived index with a rebuild path; confirm SQLite full-text support on target hosts before selecting the implementation.

**Acceptance.** A member can locate a known reply using a phrase from its body, constrain results to a person or tag, and open a stable permalink with context. Hidden records and identity records stay outside normal results. Members-only results obey the same access policy as the destination page.

**Measure.** Use a small collection of real pilot questions with known answers and observe whether members find them without help. Do not use database query speed alone as the success criterion. Discourse's documented author, category, and other filters provide a useful usability reference. [Discourse search documentation](https://meta.discourse.org/t/searching-for-content-effectively/273328)

## 3 Guided backup and device recovery

**Current behavior.** Account Key exposes copying and restoring private keys under advanced controls. Browser identity is held in local storage, and the current restore implementation rejects passphrase-protected private keys. Session recovery can reauthenticate a saved browser key; it cannot recreate a lost key. [Account page](../../templates/pages/account_key.php), [browser identity implementation](../../public/assets/browser_signing.js), and [session recovery](../plans/archive/session_reauthentication_step4_implementation_summary.md)

**Smallest useful release.** Add an understandable encrypted backup download and restore flow, a backup-status reminder, and an authenticated device-transfer flow. Build on the explicit ownership model above rather than treating matching usernames as proof of ownership. Explain the distinction between recovering site access and recovering keys needed to decrypt historical messages.

**Acceptance.** A member can establish a second browser and verify access before discarding the first. Clearing local storage is survivable with a verified backup. A lost-all-keys case has a documented result that does not promise recovery of ciphertext without the original decryption key. Private keys and recovery material remain outside public records and logs.

**Measure.** Observe a nontechnical pilot member completing backup and restore. The success condition is independent completion with correct expectations about what has been recovered.

## 4 A complete membership and moderation lifecycle

**Current behavior.** Signed approvals, invitations, reactions, and flags exist. In the current post-reaction reducer, an approved flag hides `reply-agent` content; it is not a general human-post enforcement system. Fastmod scores published content, and the backlog explicitly defers holding content for review. Operators have record-deletion and archive commands. [Flag reduction](../../src/ForumRewrite/ReadModel/ReadModelBuilder.php), [Fastmod](../reference/fast_post_scoring.md), and [CLI](../reference/v3_cli.md)

**Smallest useful release.** Provide an organizer moderation queue, member suspension/revocation, thread locking, and reversible hiding of human posts with recorded reasons. Add proportionate controls for repeated anonymous or new-member writes. Define who may act, how appeals work, and what happens to approvals made by a suspended member; do not leave transitive trust consequences implicit.

**Acceptance.** An organizer can address an abusive member and a problematic human post from the UI. Revocation affects active sessions, writes, recipient-key selection, feeds, downloads, and protected APIs. Rebuilding the read model produces the same moderation state. Public audit information excludes private reports and reporter details where appropriate.

Keep machine scoring advisory until thresholds, human overrides, and error rates have been evaluated. More scores do not replace an enforceable decision workflow.

## 5 Editing and intentional removal

**Current behavior.** Members can create posts and replies, but the inspected routes do not expose a general author-edit workflow. Operator deletion uses `git rm` and a commit; earlier versions remain in Git history and downloaded copies. [Write service](../../src/ForumRewrite/Write/LocalWriteService.php) and [delete command](../reference/v3_cli.md#delete-a-canonical-record)

**Smallest useful release.** Start with author corrections using signed revision records and visible edit history. Define a separate withdrawal and operator-removal workflow, including what disappears from default views, searches, exports, static releases, and future snapshots. Explain the limits of removing material already downloaded by others.

**Acceptance.** An author can correct an event detail without creating a contradictory replacement thread. Existing links remain meaningful. Revision authorship is verifiable. Removal is never represented as complete erasure if the content remains recoverable in an offered repository archive.

The design must reconcile two real promises: a durable record and a humane response to mistakes. A delete button without those semantics would create misleading expectations.

## 6 Finish the existing private-message conversation

**Current behavior.** Encryption, local decryption, signatures, conversation views, replies, and local compose drafts already exist. `PrivateMessageStore` limits Inbox, Sent, and a conversation to the newest 25 messages, with no cursor or older-page parameter in those methods. There is no message read state in its schema. [Conversation implementation](../plans/private_message_conversations/private_message_conversations_step4_implementation_summary.md) and [store](../../src/ForumRewrite/Messaging/PrivateMessageStore.php)

**Smallest useful release.** Add cursor pagination, “load earlier,” unread state, and reliable refresh after incoming messages. Distinguish saved locally, accepted by the server, and successfully decrypted; do not imply that server acceptance means the recipient read the message. Define backup of the private ciphertext store separately from public repository backup.

**Acceptance.** A conversation longer than 25 messages remains fully navigable without duplicates or omissions. Arrival becomes visible through the shared notification system. Missing-key and invalid-signature errors stay intelligible. Public exports, static pages, and offline public snapshots continue to exclude mailbox content.

Do this after the recipient-key contract is settled. Private messaging is partially complete, not a missing feature to start from scratch.

## 7 Everyday mobile use and writing

**Current behavior.** There are multiple presentations, mobile work, media embeds, Unicode/emoji flags, and a local Outbox. The offline runbook explicitly identifies missing draft editing, export, and navigation to a fresh offline compose page. Unicode and emoji authoring are disabled by default. Project notes also report slow navigation and reply submission; those reports need current measurement. [Offline runbook](../runbooks/offline_reading.md), [feature registry](../../src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php), and [reported friction](../../related_feature_requests.txt)

**First increments.** Make saved drafts editable and recoverable after browser restarts. Choose intentional Unicode and emoji defaults for each pilot. Observe joining, reading, replying, and recovering a draft on a phone and with keyboard or screen-reader navigation; fix the concrete failures. Instrument page delivery and accepted-post latency before promising speed improvements.

**Acceptance.** A draft survives an interrupted connection and can be revised before submission. The supported language input round-trips correctly. Critical controls remain reachable with the mobile keyboard open, and status messages are accessible. Report latency against an agreed device, network, and dataset rather than an invented universal target.

Treat file attachments as the next segment-specific addition if pilots repeatedly need images, PDFs, or recordings. Existing external media previews do not supply upload storage, access control, quotas, or deletion semantics.

## 8 Guided operation and migration

**Current behavior.** v3 already has repository downloads, a repository-import command, recovery tools, deployment runbooks, and a specialized QDB importer. The missing layer is a cohesive handover and migration experience for a community steward. [Deployment](../runbooks/production_deploy.md), [recovery](../runbooks/operator_recovery.md), and [repository import](../reference/v3_cli.md#import-a-repository-archive)

**Smallest useful release.** Provide a setup check covering required extensions, writable paths, initial trust configuration, and worker health. Add scheduled backup with a documented restore drill covering both canonical content and necessary private stores. Give a replacement steward a concise handover view. Support one migration source selected by an actual pilot, preserving attribution and parent links while labeling imported unsigned material honestly.

**Acceptance.** A second steward can restore a disposable deployment and confirm its content, membership, and private-store recovery requirements. An import dry run reports counts, collisions, and unsupported fields. Repeating an import does not duplicate records, and historical content never acquires fabricated signatures.

## Features to defer and promises to narrow

Do not prioritize more themes, more AI response modes, a native mobile app, voice/video, or broad federation ahead of the participation loop above. They may become valuable, but they do not by themselves help a member notice a reply or recover a lost identity.

Do not describe repository import as live synchronization or federation. A community can move a record without having a conflict-resolution protocol between independently writable instances.

Do not put “add offline support,” “add private messaging,” or “add backups” on the roadmap without qualification: all three already exist. The useful work is completing their boundaries and making them dependable for ordinary members. In particular, private offline reading needs a deliberate cache and revocation model; public snapshots cannot simply be reused for it.

## A sequence that can be evaluated

**First, establish trust and return paths.** Resolve composite account ownership and revocation policy; ship a personal reply inbox and basic unread state. Validate the identity and notification flows with a small existing community.

**Next, make accumulated knowledge usable.** Add general search, guided key recovery, and organizer moderation. Add author revisions after the record and removal semantics are agreed. Measure whether members can answer a real question using an older discussion and recover on another browser.

**Then, broaden adoption.** Complete message history, polish mobile drafts and language behavior, and make backup, restore, and one real migration source straightforward. Promote only the workflows that pilots can complete reliably.

The near-term product test is concrete: can a member join, contribute, notice a response, find it later, and still participate after changing devices—and can an organizer intervene when necessary?
