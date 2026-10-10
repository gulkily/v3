# Private message history synchronization Step 4 implementation summary

> **Feature plan:** [Step 1](./private_message_history_sync_step1_solution_assessment.md) · [Step 2](./private_message_history_sync_step2_feature_description.md) · [Step 3](./private_message_history_sync_step3_development_plan.md) · [Step 4](./private_message_history_sync_step4_implementation_summary.md)

Branch: `feature/private-message-history-sync`. Planning-only commit: `d7e89582`. Implementation is in progress; no merge, push, or deployment.

## Stage 1 - Cryptographic protocol validation

- Changes: added a versioned, signed, target-encrypted session-key bundle helper; account/source/target and per-message envelope-digest bindings; limits of ten entries and 64 KiB per bundle. SHA-256 supports HTTP/v5 contexts without SubtleCrypto. Reused the bundled OpenPGP library. Preserved planning findings/evidence and repaired plan navigation after grouping.
- Verification: `php tests/run.php PrivateMessageHistoryCryptoTest` passes; real v6/v5 key transfers, original-signature checks, wrong-key/account rejection, unsigned-original rejection, forwarding to a third key, and SHA-256 vectors against Node crypto. JavaScript syntax and whitespace checks pass.
- Notes: bundles contain only session keys and bindings; verification of a transfer does not verify the original message. Stages 2–8 remain. Work remains within the approved eight-stage scope; no production assets are wired yet.

## Stage 2 - Separate storage and bounded discovery

- Changes: added independently configured/lazily opened private sync SQLite storage, one-second busy timeout and FULL durability; reject identical paths, symlinks and hardlinks to the message database. Added resettable donor/account checkpoints and bounded account-wide message enumeration. Work discovery uses current approved key membership with no request queue or approval hook. Original message/unread storage remains independent.
- Verification: `php tests/run.php PrivateMessageHistorySyncTest PrivateMessageStoreTest PrivateMessageDatabaseConfigTest PrivateMessageApiRoutingTest` — 16 passed. Covers three-plus pages, both directions, foreign key rejection, approval changes, checkpoint reset, path aliases, and existing mailbox contracts. PHP syntax and whitespace checks pass.
- Notes: only the work endpoint is exposed at this stage; no browser contribution is enabled. Separate database defaults to `state/private/message_history_sync.sqlite3`; override `PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH`. Rollback journaling remains compatible with existing filesystems; concurrent API behavior will be checked in Stage 3 and the release journey.

## Stage 3 - Authenticated retained transfers and coverage

- Changes: added transfer bundles, per-source candidates, and target-reported coverage in the separate sync database; bounded same-origin JSON APIs for upload, target-only retrieval, acknowledgments, and checkpoint updates. Uploads bind current source/target membership and original message/digest. Exact retries deduplicate; unconfirmed source candidates can be replaced without suppressing other donors; acknowledged ciphertext stays reachable. Missing or mismatched originals/transfers cannot establish coverage.
- Verification: `php tests/run.php PrivateMessageHistorySyncTest PrivateMessageHistoryCryptoTest` — 8 passed. Covers revocation, cross-account/wrong-target references, request/item limits, duplicate uploads/receipts, retained ciphertext, lost transfer recovery, changed originals, and concurrent sync writers with foreground messaging. The isolated three-writer check met the local foreground p95 <250 ms criterion with no worker failures. Syntax/whitespace passed.
- Notes: local latency criterion is a regression gate, not production capacity evidence. Reads still use the original message store; all sync writes are confined to its own database with a one-second lock timeout. No cross-database atomicity is assumed. Browser wiring and end-to-end HTTP authorization checks follow in later stages.

## Stage 4 - Automatic donor visits

- Changes: wired a shared coordinator into authenticated live navigation, including non-Messages pages. It verifies accessible originals, extracts keys locally, encrypts/signs target-specific batches, uploads, and acknowledges only its own verified access. Visits are bounded to eight batches/ten seconds with request cancellation, focus throttling, checkpoint coalescing, and identity invalidation. No persistent secret cache or background worker is introduced.
- Verification: `php tests/run.php PrivateMessageHistoryCryptoTest PrivateMessageReleaseIsolationTest` — 3 passed; authenticated-navigation checks also passed. A real-key coordinator fixture verifies automatic contribution, later target decryption, original-signature preservation, and absence of plaintext/private keys in requests or browser storage writes. Static/offline HTML excludes both new assets. JavaScript syntax/whitespace passed.
- Notes: target recovery through the canonical reader follows in Stage 5; status presentation follows in Stage 7. Sync failure does not modify composer, send, or unread state.

## Stage 5 - Canonical reader recovery

- Changes: canonical message reading and list previews now retrieve authenticated target-specific candidates when direct decryption fails. Each candidate binds the current account, source/target fingerprints, message ID and exact envelope digest. Original signature checks still gate plaintext and coverage acknowledgments. Recovered keys remain ephemeral; reload retrieves retained ciphertext again. Donors can also unwrap prior transfers when obtaining access.
- Verification: `php tests/run.php PrivateMessageHistoryCryptoTest PrivateMessageReaderTest PrivateMessageListTest` — 8 passed. Real-key reader fixture covers bad donor isolation, verified restoration, unsigned-original warnings without plaintext/receipts, and a fresh coordinator rehydrating after reload. Existing reader categories, rendering, and list behavior remain passing; syntax/whitespace passed.
- Notes: a receipt failure cannot invalidate already verified reading and never marks messages seen. Candidate retrieval errors remain retryable load failures; unavailable history stays compact. Automatic recovery refresh and presentation are completed in Stages 6–7.

## Stage 6 - Reconciliation and interrupted work

- Changes: limited sync HTTP concurrency to three, added five-second request timeouts and one retry, and kept lifecycle cancellation tied to the correct visit. Exact candidate reads share in-flight work. Missing retained bundles and changed envelope digests now permit replacement even when stale receipts exist; unchanged checkpoints avoid writes. Verified recovered-key events support the next stage's UI refresh.
- Verification: `php tests/run.php PrivateMessageHistorySyncTest PrivateMessageHistoryCryptoTest` — 12 passed. Added disjoint partial donors, three-plus batches, stale-key messages sorting behind checkpoints, mismatched message/sync captures, missing retained blobs, lost acknowledgment responses, and identity changes during requests. Existing real-key recovered-device forwarding, fresh-reader reload, revocation, and concurrent foreground checks remain passing. Syntax/whitespace passed.
- Notes: restoring an older sync database can lose access unless a donor returns; coverage never reconstructs missing keys. Scans rotate across current keys and revisit unverified gaps without permanent failure rows. Candidate retrieval and acknowledgment failures remain isolated from ordinary messaging.
