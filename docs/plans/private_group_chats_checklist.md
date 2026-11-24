# Private group chats checklist

Working checklist for private group chats: three or more members talk in one conversation that nobody else can read. It is not a Railspike artifact and approves no implementation. Each item is its own Railspike cycle, staged unless marked, following AGENTS.md. Update it when a step is approved, a PR opens or merges, or the maintainer accepts a cycle; record checks and limitations here.

Requested by the maintainer on 2026-11-24 ("private group chats … a public/open group chat can already be done as a thread, but private ones are not possible yet"). Feedback items: none yet.

Goal: a member starts a group in Messages, picks members, and everyone in it reads and replies, end-to-end encrypted like DMs. A member's key that someone else approved never reads that member's messages.

## Decisions so far

- 2026-11-24: groups extend Messages, encrypted to every member's keys, stored with DMs and never in Git ([private_group_chats Step 1](./private_group_chats_step1_solution_assessment.md), Option A, not yet approved).
- 2026-11-24: approval splits into public and private. Approval still lets a key post; reading messages needs a same-username key with private access or an operator ([private_key_access Step 1](./private_key_access/private_key_access_step1_solution_assessment.md)).
- 2026-11-24: existing keys that fail the private-access rule are downgraded to public only, not grandfathered.

## Reuse inventory

Found on a first read at `2d7805c8`. Each cycle re-checks its own part before planning.

- `src/ForumRewrite/Messaging/ApprovedUserKeyResolver.php` picks the keys a message is encrypted to: today every approved key of a username.
- `PrivateMessageHistorySyncService.php` shares older messages with new keys, from the account's own devices and from senders; it checks `is_approved` only.
- `PrivateMessageMailboxService.php` (`approvedViewer`) gates mailbox access on `is_approved` only.
- `PrivateMessageStore.php` keeps envelopes in private SQLite (`private_messages`: sender, recipient, envelope), outside Git, the read model, static pages and the offline snapshot.
- `src/ForumRewrite/Security/ApprovalPolicy.php` and the receipts in `records/approval-acceptances/` record each grant's classification (`new_account`, `own_account_key`, `cross_account_key`) and operator authority.
- `FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL` (on by default) lets any approved member approve a key for another existing username.
- Browser side: `public/assets/private_message_compose.js`, `private_message_conversation.js`, `private_message_reader.js`, `private_message_cache.js` (saved copies), `private_message_history_sync.js`, `private_message_unread.js`.
- `docs/product/v3_feature_priorities.md` names recipient-key control as the release gate before group messages.

## Stopgap

- [ ] Maintainer decides whether to turn `FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL` off on zenmemes.com until cycle 1 ships. It stops new cross-account keys; existing ones keep access until cycle 1.

## Cycle 1 — `private_key_access` (staged; estimate 0.95)

A key gets private access only if it is the username's first key, was approved by a same-username key with private access, was approved by an operator, or was later confirmed by one of those. Ships before any group work; fixes today's DM exposure on its own.

- [x] Step 1 written, Option A recommended (receipts plus a signed confirm record), 2026-11-24
- [x] Step 1 Revision 1: how the owner finds out (device line with short code, QR confirm link, Confirm / Not me card on Unread and Messages, operator path); estimate 0.90, 2026-11-24
- [x] Step 1 approved, 2026-11-24
- [x] Step 2 written, 2026-11-24: Not me reuses key retirement; release order is flag off, deploy, confirm own devices from User Access, flag on
- [x] Step 2 approved, 2026-11-24
- [x] Step 3 written: eight stages, estimate 0.95, 2026-11-24
- [x] Step 3 approved; plans moved to `docs/plans/private_key_access/` and committed on `feat/private-key-access`, 2026-11-24
- [x] Stage 1: the check and `./v3 approval private-access-report`. A local import shows 28 `ilyag` keys public only, restored by confirming 10 chain roots ([summary](./private_key_access/private_key_access_step4_implementation_summary.md#stage-1---the-private-access-check))
- [x] Stage 2: signed confirm record and API; rule 3 now trusts receipts. Import report: 77 public only, 17 of them `ilyag`
- [x] Stage 3: enforcement in recipient keys, mailbox and history sync, behind `FORUM_PRIVATE_KEY_ACCESS_ENFORCED`
- [x] Stage 4: "This device can't read messages yet" with code and QR on Messages
- [x] Stage 5: Confirm / Not me card on Unread and Messages, and `/account/confirm/<fingerprint>` for the QR; confirmed end to end in Chrome
- [x] Stage 6: "Waiting for private access" in Tools → User Access, Confirm for operators only
- [x] Stage 7: concept entry, agent API, CLI reference, runbook, release-gate progress
- [ ] PR opened
- [ ] Stage 8 on zenmemes.com (maintainer): flag off, deploy, report, confirm own devices, flag on, replay a downgraded device by QR
- [ ] Follow-up idea: list chain roots first on the card (10 confirms restore all 17 `ilyag` keys)
- [ ] Before deploy: list the zenmemes.com keys the downgrade affects; maintainer confirms his own devices
- [ ] PR opened, merged, deployed
- [ ] Maintainer accepts: a new same-name key approved by someone else shows Public only and gets no DMs; confirming it from his phone brings new messages and history

## Cycle 2 — `private_group_chats` (staged; estimate 0.70)

Option A1: groups with fixed membership. The creator picks members once; any member can leave. Needs cycle 1.

- [x] Step 1 written, Option A1 recommended, 2026-11-24; Revision 1 adds the cycle 1 dependency
- [ ] Step 1 approved
- [ ] Step 2 questions: who may start a group, size cap (proposed 20), group name, agent support
- [ ] Step 2 approved
- [ ] Step 3 approved; plans moved to `docs/plans/private_group_chats/` and committed
- [ ] Early check: encrypting to about 40 keys on the iPhone SE stays quick
- [ ] PR opened, merged, deployed behind `FORUM_GROUP_MESSAGES_ENABLED`
- [ ] Maintainer accepts: a three-person group sends, reads and replies; an outsider cannot list it; a member who leaves gets no new messages

## Cycle 3 — group membership changes (staged; not started)

- [ ] Step 1: who may add and remove members; renaming a group
- [ ] Removing a member stops future encryption to them; what they already read stays readable to them

## Cycle 4 — history for new members (staged; not started)

- [ ] Step 1: extend history sync so a current member's browser shares older group messages with a new member, or decide new members start from their join

## Later, not planned

- Quill confirms a key's private access: [PF-20261124-F01](../feedback/items/PF-20261124-F01.txt), after cycle 1
- Quill and agent support for groups
- Notifications for group messages (see [notifications checklist](./notifications_checklist.md))
- Showing members each other's key lists, Signal-style
