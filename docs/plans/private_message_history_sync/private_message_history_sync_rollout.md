# Private message history sync: API and operations

Related: [requirements](./private_message_history_sync_step2_feature_description.md) · [plan](./private_message_history_sync_step3_development_plan.md) · [implementation and evidence](./private_message_history_sync_step4_implementation_summary.md) · [storage findings](./private_message_history_sync_storage_findings.md).

## Storage and deployment

Deploy PHP and browser assets together. Messages and unread state remain in `PRIVATE_MESSAGE_DATABASE_PATH` (default `state/private/messages.sqlite3`). Sync uses its own lazy connection to `PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH` (default `state/private/message_history_sync.sqlite3`). Configure both paths outside the document root, canonical repository, static releases, offline snapshots, and public backups. Identical paths, symlinks and hardlinks to the message database are rejected. New directories use mode 0700 and the sync file 0600; preserve private permissions for backups and SQLite sidecars too.

The first authenticated sync request creates `history_sync_transfers`, `history_sync_items`, `history_sync_coverage`, and `history_sync_scan`. No original-message migration or approval-event replay is required: existing approved keys are discovered too. The sync connection uses a one-second busy timeout and FULL synchronous durability, retaining SQLite's default rollback journal on newly created files. Operators should preserve this policy; separate files isolate writer locks, not disk load or original-message reads. There are no attached databases, cross-store foreign keys, or cross-store transactions.

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

An authenticated live visit can contribute without opening Messages. A visit scans at most eight batches for approximately ten seconds, with a fifteen-second cancellation deadline; individual HTTP requests time out after five seconds and retry once, with at most three sync requests in flight. Focus/visible return continues work (five-second throttle); hidden tabs, navigation and identity changes cancel stale work. Explicit Retry history starts another bounded pass and rereads unavailable visible content. There is no continuous polling, job queue, lease service, permanent failure row or persistent secret cache.

Devices need not be online together. Retained bundles permit reading after reload and allow a restored device to help later approved keys. Recovery depends on at least one eligible device retaining access; a status check does not prove every historical message is recoverable. Visit-local verified counts exclude mere uploads. Normal message ordering, drafts, sending, signature warnings, compact unavailable groups and unread/seen boundaries remain controlled by their existing components. Revocation prevents future eligible transfer use, but cannot retract already delivered secrets.

## Coordinated private backup and restore

1. Resolve and record **both configured absolute paths** and the corresponding account/approval repository state. Preserve file ownership and private permissions. Take private backups outside release artifacts.
2. Quiesce message sends and sync writes and drain active requests. Use SQLite's backup API or an equivalent consistent snapshot for each file while writes remain stopped. A live bare `cp` can miss transactions or journal/WAL state. Keeping writes stopped across both captures gives a common logical backup point; the application does not provide cross-database atomic snapshots.
3. Verify each backup with `PRAGMA integrity_check`; retain the capture times/configuration. Back up the canonical identity/approval repository through its existing procedure. Resume writes after both private captures finish.
4. For restore, stop/drain writers again, restore the matching pair and compatible identity/approval state, and handle journals using SQLite's supported restore procedure. Preserve private permissions, then start the matching code/assets and run the smoke journey below.
5. With mismatched captures, stable message IDs and envelope digests reject orphaned or changed references. Missing transfer ciphertext reopens coverage; missing scan anchors restart discovery. Allow donor visits to reconcile and inspect remaining unavailable messages. Older sync captures may permanently lose the only retained transfer if no eligible donor can return; receipts cannot reconstruct it. Existing unread metadata has its own restore constraints—see [unread rollout](../private_message_unread/private_message_unread_rollout.md).

Rollback code and assets together to the preceding version while **retaining both databases**. The prior application continues using the unchanged message store and ignores the separate sync file. New keys can lose historical reading while the old reader is deployed; this is a reversible code limitation, not a reason to delete transfers. Re-upgrading reuses retained bundles and current membership, resumes scans, and discovers messages delivered during rollback. Do not copy sync tables into the message database.

## Deployment smoke and evidence boundary

In a controlled environment, publish and approve a new same-account key through the normal signed approval path. Verify pending access is denied, an authenticated donor visit on a non-Messages page contributes, and the target later reads sent/received originals across at least three batches with the donor closed. Exercise partial donors, retry, reload, mobile/keyboard, and restored-device forwarding. Confirm originals are unchanged and uploads alone do not clear unavailable state or unread counts.

Temporarily make only the sync store unavailable: sync should report a retryable error while ordinary sends/direct reads remain usable. Restore it and verify retained recovery. Rehearse paired and mismatched restore plus rollback, inspect no-store/auth/origin checks, and scan logs/public artifacts for private data. Run:

```sh
php tests/run.php PrivateMessageHistoryCryptoTest PrivateMessageHistorySyncTest PrivateMessageReaderTest PrivateMessageListTest PrivateMessageReleaseIsolationTest
node tests/browser/private_message_history_sync_browser.mjs
node tests/browser/private_message_list_browser.mjs
```

Automated local Chromium covers the real approval journey, partial donors, retained reload/forwarding, outage, paired restore, and existing messaging regressions. PHP tests cover concurrent writers, stale-key messages, aliases, revoked/foreign keys and mismatched restores. These are local checks, not production capacity or deployment evidence. Physical mobile keyboards and other browser engines remain manual follow-ups. No production deployment was performed.
