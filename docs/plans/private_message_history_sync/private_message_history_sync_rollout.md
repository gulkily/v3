# Private message history sync: how it works and how to operate it

Related: [requirements](./private_message_history_sync_step2_feature_description.md) · [plan](./private_message_history_sync_step3_development_plan.md) · [implementation and evidence](./private_message_history_sync_step4_implementation_summary.md) · [storage findings](./private_message_history_sync_storage_findings.md).

## What the feature does

Private message history sync helps an approved new device read older messages by obtaining encrypted access from another device belonging to the same account. It covers sent and received messages and runs automatically during authenticated visits, without a sync banner or a separate sharing prompt. It is implemented and merged into local `main`; deployment is a separate step.

For example, Alice's laptop can read her old conversations, but her newly approved phone cannot. Alice visits the site on the laptop, then later opens Messages on the phone. The laptop leaves encrypted recovery material for the phone, allowing the phone to read and verify the original messages. The two devices do not need to be online together.

## How recovery happens

1. **A key is approved for the account.** The application treats currently approved keys sharing the normalized username as that account's keys. All those keys are authorized for its history, including keys approved before this feature existed.
2. **A device with access visits the site.** It can contribute from an ordinary authenticated page; opening Messages on that device is unnecessary. The browser checks a bounded portion of history and verifies messages it can already decrypt.
3. **The browser prepares encrypted access.** Each original message has a session key that unlocks its encrypted contents. The contributing browser signs a bundle of these keys and encrypts it specifically for an approved target device. The bundle identifies the account, source and target keys, and the exact original messages.
4. **The server retains the encrypted bundle.** This is private recovery data, separate from conversations. It creates no chat messages and does not mark messages read. Original encrypted messages remain unchanged.
5. **The target reads the originals.** Its browser decrypts the bundle with its own private key, checks the donor and message bindings, then decrypts each original message and verifies the original sender's signature before displaying trusted text. A donor's signature alone is insufficient.

Transfers stay available after successful recovery, so reloading the target does not require another donor visit. Multiple devices can supply different parts of history, and a recovered device can help later approved devices. Large histories progress across bounded visits and visible returns; recovery is not guaranteed to finish in one visit. Existing per-message unavailable states and reading retries remain available.

## What is protected, and what remains limited

- Private identity keys stay in their browsers. Message plaintext and unwrapped session keys are not sent to the server or added to persistent recovery caches.
- The server stores encrypted messages, encrypted recovery bundles and synchronization metadata. It can see account, key and message relationships; this does not hide that metadata from the operator.
- Only currently approved same-account keys participate. Removing approval prevents future eligible transfer use, but cannot retract secrets a device already received.
- Recovery requires an eligible device with suitable access or a retained eligible transfer. If neither exists, approval alone cannot restore history. Original messages must also still exist on the server.
- This release does **not** recover history from the other person's devices. Sender-assisted recovery is a separate proposal.
- Messages/unread data and recovery data use separate private databases. A sync-store outage can interrupt historical recovery while ordinary sending and direct reading continue. Both stores need coordinated private backups; neither belongs in public downloads or offline snapshots.

The remaining sections document the API, exact limits, deployment, backup and rollback for technical readers and operators.

## Storage and deployment

Deploy PHP and browser assets together. Messages and unread state remain in `PRIVATE_MESSAGE_DATABASE_PATH` (default `state/private/messages.sqlite3`). Sync uses its own lazy connection to `PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH` (default `state/private/message_history_sync.sqlite3`). Configure both paths outside the document root, canonical repository, static releases, offline snapshots, and public backups. Identical paths, symlinks and hardlinks to the message database are rejected. New directories use mode 0700 and the sync file 0600; preserve private permissions for backups and SQLite sidecars too.

The first authenticated sync request creates `history_sync_transfers`, `history_sync_items`, `history_sync_coverage`, `history_sync_scan`, and `history_sync_sender_scan`. The sender-assisted upgrade adds only the last table, with independent per-source sender checkpoints; existing transfer rows, account checkpoints and the original-message schema are preserved. No original-message migration or approval-event replay is required: existing approved keys are discovered too. The sync connection uses a one-second busy timeout and FULL synchronous durability, retaining SQLite's default rollback journal on newly created files. Operators should preserve this policy; separate files isolate writer locks, not disk load or original-message reads. There are no attached databases, cross-store foreign keys, or cross-store transactions.

Ordinary messaging initializes only its existing store. A missing/unwritable/unavailable sync path yields a generic retryable 503; Messages can still send and directly read messages. Never repair a sync error by deleting the retained transfers. A missing file can be recreated, but old coverage alone cannot recover the lost keys.

## API contract

All endpoints require the existing authenticated, currently approved identity and return `Cache-Control: no-store` on successes and errors. Scope derives from the authenticated account/key, never a supplied account/source field. POST requires JSON, `X-Requested-With: ForumPrivateMessages`, and rejects cross-site/same-site fetches. Request bodies are limited to 100,000 bytes; unknown methods/actions return 405, unavailable authentication 401/403, invalid input 400, and unavailable storage 503. Responses use `{status:"ok", ...}` or `{status:"error", error:...}`.

| Endpoint | Request | Successful response |
| --- | --- | --- |
| GET `/api/private_messages/history_sync/work` | No parameters | `account`, `source`, `target`, `target_key`, `messages`, `checkpoint:{target,after_id}`, `cycle_end`, `total` |
| POST `/api/private_messages/history_sync/transfers` | `target`, armored `ciphertext`, `items:[{message_id,digest}]` | `transfer_id` |
| GET `/api/private_messages/history_sync/transfers` | `message_id`, optional `after` transfer ID | Current `account`, `target`, `message_id`, `digest`, candidate `transfers`, nullable `next` |
| POST `/api/private_messages/history_sync/acknowledge` | `items:[{message_id,digest,transfer_id?}]`, optional `checkpoint:{target,after_id}` | `confirmed` item count |

Work scans up to ten originals and about 2 MiB per page, advancing past covered/unavailable rows. A message contains its original encrypted envelope, sender keys and SHA-256 digest. Checkpoints rotate through current approved targets; a missing original anchor resets the scan. `total` counts account messages, not recovered messages. Retrieval returns up to ten candidates with source/target/account, source public key, transfer ID and ciphertext. Pagination advances through revoked-source rows without exposing them.

Uploads have at most ten distinct current message/digest bindings and 65,536 bytes of armored ciphertext. Exact upload retries deduplicate. Unconfirmed candidates from the same donor can be replaced; verified retained candidates stay reachable. Changed digests and missing retained blobs reopen work. Different donors remain independent. Acknowledgments report the target's verified access; an empty transfer ID reports direct access. They are scheduling hints and do not mark messages seen. Acknowledgment never deletes ciphertext. Original IDs/digests are rechecked on reads and writes, tolerating capture skew between the two databases.

Version 1's signed, target-encrypted JSON is `{version:1,account,source,target,entries:[{message_id,digest,keys:[{algorithm,data}]}]}`. Fingerprints are full lowercase OpenPGP fingerprints; `data` is base64 session-key bytes. Browsers verify signer and target fingerprints, current account/key bindings and original envelope digest, then decrypt and verify the **original sender signature** before showing plaintext or reporting coverage. Bad candidates, unsigned originals and failed signatures cannot establish verified reading. Server indexes expose account/key/message relationships and encrypted bundles, never plaintext or unwrapped keys. Both bundled OpenPGP versions have real-key protocol coverage.

## Browser behavior and limits

An authenticated live visit can contribute without opening Messages. A visit scans at most eight batches for approximately ten seconds, with a fifteen-second cancellation deadline; individual HTTP requests time out after five seconds and retry once, with at most three sync requests in flight. Focus/visible return continues work (five-second throttle); hidden tabs, navigation and identity changes cancel stale work. Synchronization runs without a global notice or Retry history button; existing per-message reading retries remain available. There is no continuous polling, job queue, lease service, permanent failure row or persistent secret cache.

Devices need not be online together. Retained bundles permit reading after reload and allow a restored device to help later approved keys. Recovery depends on at least one eligible device retaining access; a status check does not prove every historical message is recoverable. Only successful original-message verification establishes readable content; uploads alone do not. Normal message ordering, drafts, sending, signature warnings, compact unavailable groups and unread/seen boundaries remain controlled by their existing components. Revocation prevents future eligible transfer use, but cannot retract already delivered secrets.

## Coordinated private backup and restore

1. Resolve and record **both configured absolute paths** and the corresponding account/approval repository state. Preserve file ownership and private permissions. Take private backups outside release artifacts.
2. Quiesce message sends and sync writes and drain active requests. Use SQLite's backup API or an equivalent consistent snapshot for each file while writes remain stopped. A live bare `cp` can miss transactions or journal/WAL state. Keeping writes stopped across both captures gives a common logical backup point; the application does not provide cross-database atomic snapshots.
3. Verify each backup with `PRAGMA integrity_check`; retain the capture times/configuration. Back up the canonical identity/approval repository through its existing procedure. Resume writes after both private captures finish.
4. For restore, stop/drain writers again, restore the matching pair and compatible identity/approval state, and handle journals using SQLite's supported restore procedure. Preserve private permissions, then start the matching code/assets and run the smoke journey below.
5. With mismatched captures, stable message IDs and envelope digests reject orphaned or changed references. Missing transfer ciphertext reopens coverage; missing scan anchors restart discovery. Allow donor visits to reconcile and inspect remaining unavailable messages. Older sync captures may permanently lose the only retained transfer if no eligible donor can return; receipts cannot reconstruct it. Existing unread metadata has its own restore constraints—see [unread rollout](../private_message_unread/private_message_unread_rollout.md).

Rollback code and assets together while **retaining both databases**. Rolling back sender assistance to the same-account release preserves v1 recovery and ignores the additive sender checkpoint table; its backend excludes foreign-account donors and its browser rejects v2 bundles. History dependent only on sender assistance becomes unavailable until re-upgrade. Rolling back further, to pre-sync code, disables all transfer-based recovery. These are code limitations, not reasons to delete transfers. Re-upgrading reuses retained bundles/current membership and resumes scans. Do not copy sync tables into the message database.

Mixed versions fail conservatively: old clients retain v1 same-account recovery against the new backend but cannot consume v2 sender bundles; new clients against the old backend detect unsupported sender work and continue account work. Deploy PHP and assets together for the full feature. A rollback does not revoke secrets already delivered to browsers.

## Deployment smoke and evidence boundary

In a controlled environment, publish and approve a new same-account key through the normal signed approval path. Verify pending access is denied, an authenticated donor visit on a non-Messages page contributes, and the target later reads sent/received originals across at least three batches with the donor closed. Exercise partial donors, retry, reload, mobile/keyboard, and restored-device forwarding. Confirm originals are unchanged and uploads alone do not clear unavailable state or unread counts.

Temporarily make only the sync store unavailable: sync APIs should return a retryable error while ordinary sends/direct reads remain usable, without a global sync banner. Restore it and verify retained recovery. Rehearse paired and mismatched restore plus rollback, inspect no-store/auth/origin checks, and scan logs/public artifacts for private data. Run:

```sh
php tests/run.php PrivateMessageHistoryCryptoTest PrivateMessageHistorySyncTest PrivateMessageReaderTest PrivateMessageListTest PrivateMessageReleaseIsolationTest
node tests/browser/private_message_history_sync_browser.mjs
node tests/browser/private_message_list_browser.mjs
```

Automated local Chromium covers the real approval journey, partial donors, retained reload/forwarding, outage, paired restore, and existing messaging regressions. PHP tests cover concurrent writers, stale-key messages, aliases, revoked/foreign keys and mismatched restores. These are local checks, not production capacity or deployment evidence. Physical mobile keyboards and other browser engines remain manual follow-ups. No production deployment was performed.
