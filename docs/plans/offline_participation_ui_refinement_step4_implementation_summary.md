# Offline Participation UI Refinement Step 4 Implementation Summary

## Stage 1 - Define signed offline intents and timestamps
- Changes:
  - Extended local Outbox items with action time, integration time, and a private signed-intent envelope while keeping those fields out of safe summaries.
  - Added a canonical offline-intent builder that binds a stable ID, action, target, action time, author identity, and payload before requesting a detached browser signature.
- Verification:
  - `node --check public/assets/outbox_intent.js` and PHP syntax checks passed.
  - `php tests/run.php OfflineOutboxStateTest OfflineOutboxIntentTest` — 4 run, 4 passed.
- Notes:
  - This stage only creates a local signed envelope; reaction API verification and UI capture arrive in later stages.

## Stage 2 - Verify and integrate signed reactions
- Changes:
  - Added a signed-reaction endpoint that verifies the browser's detached signature, binds its target/payload/author, and accepts only Likes.
  - Persisted signed action time and stable intent ID on new thread-label and post-reaction records while retaining server-created time as the integration time used by existing derived state.
- Verification:
  - PHP syntax checks passed.
  - `php tests/run.php CanonicalRecordParsersTest WriteApiSmokeTest::testApplyThreadTagApiDuplicateShortCircuitsWithoutNewRecord WriteApiSmokeTest::testApplyPostTagApiDuplicateShortCircuitsWithoutNewRecord WriteApiSmokeTest::testSignedReactionApiVerifiesCommentLikeAndRetainsBothTimes` — 38 run, 38 passed.
- Notes:
  - Existing unsigned reaction endpoints and legacy records remain supported; offline UI capture switches to the signed endpoint in Stage 4.

## Stage 3 - Match supported snapshot card presentation
- Changes:
  - Brought snapshot thread roots and comments into the online card contract: stable post IDs/data, root/comment metadata ordering, preserved body line breaks, and post permalinks.
  - Added a renderer contract test for the online-equivalent root/comment structure.
- Verification:
  - `node --check public/assets/offline_reader.js` and PHP syntax checks passed.
  - `php tests/run.php OfflineSnapshotPresentationTest OfflineSnapshotThreadPresentationTest` — 7 run, 7 passed.
- Notes:
  - Like controls are deliberately added in Stage 4, after the signed-action contract is available.

## Stage 4 - Queue signed Likes from roots and comments
- Changes:
  - Loaded browser signing and the signed-intent contract into the offline reader shell.
  - Replaced the offline-only Queue Like control with online-style Like controls for both saved roots and comments; successful click-time signing queues a local intent and changes only the local button state.
- Verification:
  - JavaScript/PHP syntax checks passed.
  - `php tests/run.php OfflineOutboxStateTest OfflineOutboxIntentTest OfflineSnapshotThreadPresentationTest LocalAppSmokeTest::testOfflineReaderFallbackRouteUsesLocalSnapshotShell` — 7 run, 7 passed.
- Notes:
  - A missing locally saved identity reports a signing error and does not create an unsigned Like; automatic delivery is Stage 5.

## Stage 5 - Process queued work on foreground reconnect
- Changes:
  - Added a foreground queue processor for queued/waiting items, guarded by a browser delivery lock and persisted sending state; drafts are never eligible.
  - Switched signed reaction delivery to the verified signed-reaction API and retained its authoritative integration time locally.
  - Started foreground processing on Tools → Outbox load/reconnect and offline-reader reconnect, without Background Sync.
- Verification:
  - JavaScript/PHP syntax checks passed.
  - `php tests/run.php OfflineOutboxSendTest OfflineOutboxIntentTest LocalAppSmokeTest::testOfflineReaderFallbackRouteUsesLocalSnapshotShell` — 4 run, 4 passed.
- Notes:
  - A closed browser page does not send work; Background Sync remains deliberately out of scope.

## Stage 6 - Compact expandable Outbox rows
- Changes:
  - Replaced multi-line Outbox cards with one collapsed, expandable row per item, retaining action, state, and safe summary in the row label.
  - Moved timestamps, outcomes, recovery explanation, published links, and controls into expansion detail; signed action time and integration time are labelled separately.
- Verification:
  - `node --check public/assets/outbox.js` and PHP syntax checks passed.
  - `php tests/run.php OfflineOutboxPresentationTest LocalAppSmokeTest::testOutboxRouteIsAvailableFromToolsWithLocalStorageAssets` — 2 run, 2 passed.
- Notes:
  - Private local payload remains absent from both collapsed and expanded presentation.

## Stage 7 - Documentation and release verification
- Changes:
  - Updated the offline reading runbook, MVP checklist, and both offline roadmaps for compact Outbox rows, signed thread/reply Likes, distinct action and integration times, and foreground automatic queued delivery.
  - Documented the delivery boundary: queued work can be processed only while an Outbox or offline-reader page is open; drafts are never sent automatically and a closed browser has no Background Sync delivery.
- Verification:
  - `node --check public/assets/outbox.js`, `node --check public/assets/outbox_sender.js`, and `node --check public/assets/offline_reader.js` passed.
  - `php tests/run.php OfflineOutboxStateTest OfflineOutboxIntentTest OfflineOutboxSendTest OfflineOutboxPresentationTest OfflineSnapshotPresentationTest OfflineSnapshotThreadPresentationTest LocalAppSmokeTest::testOfflineReaderFallbackRouteUsesLocalSnapshotShell LocalAppSmokeTest::testOutboxRouteIsAvailableFromToolsWithLocalStorageAssets WriteApiSmokeTest::testSignedReactionApiVerifiesCommentLikeAndRetainsBothTimes` — 18 run, 18 passed.
