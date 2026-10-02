# Offline Outbox Step 4 Implementation Summary

## Stage 1 - Define durable Outbox state
- Changes:
  - Added a browser-neutral Outbox item contract for the three MVP action types: reaction, reply, and thread.
  - Added explicit state transitions, pending counts, and payload-free safe summaries so local intent cannot be mistaken for published content.
- Verification:
  - `node --check public/assets/outbox_store.js` and PHP syntax checks passed.
  - `php tests/run.php OfflineOutboxStateTest` — 2 run, 2 passed.
- Notes:
  - This stage defines no persistence or UI; those arrive in Stages 2-3.

## Stage 2 - Persist local work safely
- Changes:
  - Added IndexedDB-backed Outbox storage with list, load, save, remove, and clear operations, separate from the public offline-reader cache.
  - Added actionable unavailable-storage, security, and quota messages for a visible recovery path in later UI stages.
- Verification:
  - `node --check public/assets/outbox_storage.js` and PHP syntax checks passed.
  - `php tests/run.php OfflineOutboxStateTest OfflineOutboxStorageTest` — 3 run, 3 passed.
- Notes:
  - Storage is not yet loaded by a page; Stage 3 will expose it through Tools → Outbox.

## Stage 3 - Expose Tools → Outbox offline
- Changes:
  - Added Tools → Outbox with a local-item/empty-state presentation backed by the dedicated Outbox store.
  - Added a dedicated cached Outbox shell and offline navigation fallback without putting local items into the public snapshot cache.
  - Narrowed service-worker asset discovery to scripts, stylesheets, and runtime assets so page links are not cached as resources.
- Verification:
  - JavaScript and PHP syntax checks passed.
  - `php tests/run.php OfflineNavigationWorkerTest LocalAppSmokeTest::testOutboxRouteIsAvailableFromToolsWithLocalStorageAssets` — 4 run, 4 passed.
- Notes:
  - Stage 3 only observes stored items; action capture and send controls arrive in later stages.

## Stage 4 - Prove queueing with supported votes
- Changes:
  - Added Queue Like to saved thread reading, creating a local queued thread-like intent without changing the snapshot score or claiming server acceptance.
  - Added stable local intent IDs and loaded the Outbox contract/storage with the offline reader.
- Verification:
  - JavaScript/PHP syntax checks passed.
  - `php tests/run.php OfflineOutboxStateTest OfflineSnapshotPresentationTest LocalAppSmokeTest::testOfflineReaderFallbackRouteUsesLocalSnapshotShell` — 10 run, 10 passed.
- Notes:
  - Only Like is queueable in this stage; sending and server reconciliation are intentionally deferred to Stage 7.

## Stage 5 - Capture reply drafts and queued replies
- Changes:
  - Added Save reply to Outbox on reply compose, storing an editable local reply draft with thread and parent context.
  - Loaded the Outbox contract/storage on compose pages without preparing, signing, or submitting the reply.
- Verification:
  - JavaScript/PHP syntax checks passed.
  - `php tests/run.php OfflineOutboxComposeTest OfflineOutboxStateTest` — 4 run, 4 passed.
- Notes:
  - This stage captures replies from an already-open compose page; direct offline compose navigation and explicit send/retry remain later work.

## Stage 6 - Capture new-thread drafts and queued threads
- Changes:
  - Added Save thread to Outbox on new-thread compose, retaining the subject, body, and board tags as a local draft.
  - Kept capture local-only: it neither prepares nor signs nor submits a thread.
- Verification:
  - `node --check public/assets/outbox_compose.js` and PHP syntax checks passed.
  - `php tests/run.php OfflineOutboxComposeTest OfflineOutboxStateTest` — 5 run, 5 passed.
- Notes:
  - This is available from an already-open compose page; explicit sending, retry, and reconciliation remain Stage 7 work.

## Stage 7 - Explicit send and reconciliation
- Changes:
  - Added Outbox queue, send/retry, discard, and remove controls. Sending is always initiated from Tools → Outbox; reconnecting alone cannot submit content.
  - Reused the existing browser identity, prepare, detached-signature, and finalize APIs for reply and thread delivery; queued Likes use the existing reaction API.
  - Retained prepared post metadata privately in the local item while delivery is uncertain. A retry finalizes that same prepared post, and a missing prepared record is checked by post ID before accepting an interrupted response, avoiding a duplicate post.
  - Added accepted, rejected, conflicted, waiting-for-connection, and needs-attention outcomes with the server/local error available in the Outbox.
- Verification:
  - JavaScript and PHP syntax checks passed.
  - `php tests/run.php OfflineOutboxSendTest OfflineOutboxStateTest OfflineOutboxComposeTest LocalAppSmokeTest::testOutboxRouteIsAvailableFromToolsWithLocalStorageAssets` — 7 run, 7 passed.
- Notes:
  - Delivery remains user-triggered; Background Sync is intentionally not used to submit composed content.

## Stage 8 - Document and release-test the MVP
- Changes:
  - Added the local-Outbox lifecycle, privacy boundary, recovery guidance, and current limits to the Offline Reading Runbook.
  - Updated both offline roadmaps and the four-feature MVP checklist to distinguish delivered capture/send behavior from remaining edit, export, direct-compose, and server-idempotency work.
  - Made Outbox timestamps/state explanations explicit and linked accepted replies/threads to their canonical server records.
- Verification:
  - `node --check public/assets/outbox.js` passed.
  - Focused suite: `php tests/run.php OfflineOutboxStateTest OfflineOutboxStorageTest OfflineOutboxComposeTest OfflineOutboxSendTest OfflineNavigationWorkerTest LocalAppSmokeTest::testOutboxRouteIsAvailableFromToolsWithLocalStorageAssets BrowserSigningNormalizationTest` — Outbox-focused checks passed; two compose timing-fixture tests reported `state.resolveCreatePreparedPost is not a function`.
  - Full `php tests/run.php` completed. In addition to those two compose timing-fixture failures, it reported failures in anonymous-board session handling, adjacent signature-file links, SQLite Viewer routing, and incremental approval/rebuild parity; none cover Outbox behavior.
- Notes:
  - The remaining unchecked MVP items are intentionally retained in the checklist rather than represented as delivered parity.
