# Offline Reading Release 1 Step 4 Implementation Summary

## Stage 1 - Public SQLite snapshot
- Changes:
  - Added a bounded, public-only offline snapshot builder.
  - Added fixture coverage for recency, visible replies, omitted hidden/bootstrap content, and omitted workflow tables.
  - Grouped the feature plans in the plans index.
- Verification:
  - `php -l src/ForumRewrite/Offline/PublicOfflineSnapshotBuilder.php`
  - `php -l tests/PublicOfflineSnapshotBuilderTest.php`
  - `php tests/run.php PublicOfflineSnapshotBuilderTest` — passed (1/1).
- Notes:
  - Snapshot storage is capped at 10 MiB; over-cap content stops at complete-thread boundaries and records the actual cached count.

## Stage 2 - Snapshot release delivery
- Changes:
  - Added the public snapshot to each static release and exposed it at `/offline/snapshot.sqlite3`.
  - Kept the resource behind the existing approved-members gate when private mode is enabled.
- Verification:
  - PHP lint passed for the changed host, builder, application, and smoke-test files.
  - Direct front-controller checks passed for public delivery and private-mode non-delivery.
  - `php tests/run.php PublicOfflineSnapshotBuilderTest` — passed (1/1).
  - Full `LocalAppSmokeTest` ran with 95/100 passing; its five activity/signature failures predate this work and are recorded as long-standing by the test runner.
- Notes:
  - The resource has a stable URL but always resolves against the active static release.

## Stage 3 - Offline reader shell
- Changes:
  - Added the shared-layout `/offline/` reader shell and normal navigation entry.
  - Added a local SQLite snapshot loader with generation/failure status and no query-editor surface.
- Verification:
  - PHP lint passed for the controller, application, renderer, and smoke test.
  - `node --check public/assets/offline_reader.js` — passed.
  - Direct `LocalAppSmokeTest::testOfflineReaderRouteUsesLocalSnapshotShell` — passed.
- Notes:
  - The reader loads `/offline/snapshot.sqlite3` without credentials and emits a readiness event for the next two presentation stages.

## Stage 4 - Local recent-thread list
- Changes:
  - Added local recent-thread querying, saved-thread metadata, cached timestamp, and empty state.
  - Thread controls emit local selection events without requesting a server page.
- Verification:
  - `node --check public/assets/offline_reader.js` — passed.
  - Chromium headless smoke test against a locally generated snapshot rendered `Offline snapshot is ready.`, the snapshot time, and saved thread controls.
- Notes:
  - The list follows snapshot activity order and does not imply that older threads are unavailable online.

## Stage 5 - Local thread reader
- Changes:
  - Added snapshot-only thread detail, ordered replies, hash-addressable selection, and back-to-list navigation.
  - Added explicit online-only treatment for posting, voting, and tagging.
- Verification:
  - `node --check public/assets/offline_reader.js` — passed.
  - Chromium headless smoke test loaded a selected snapshot thread, its content, the back control, and the online-only notice.
- Notes:
  - A thread absent from the snapshot returns to the saved-thread list with an explicit unavailable state.

## Stage 6 - Offline cache and installation
- Changes:
  - Added a public-only web app manifest, service worker, and registration script.
  - The service worker caches the offline reader shell, its same-origin assets, SQLite runtime, and snapshot; refreshes fetch all resources before replacing the saved snapshot.
  - Kept the worker, manifest, and cache-registration path out of approved-members-only layouts and limited static-server bypasses to those public files.
- Verification:
  - `node --check public/service_worker.js` and `node --check public/assets/pwa_registration.js` — passed.
  - `LocalAppSmokeTest::testPublicLayoutIncludesOfflineReaderPwaAssets` — passed.
  - `php tests/run.php WebServerRoutingTest` — passed (2/2).
- Notes:
  - The cache strategy does not intercept API, account, post, or other personalized routes. A local headless Chromium profile could not be persisted in this environment (Snap confinement), so a normal-browser offline restart check remains for Stage 7.

## Stage 7 - Privacy regression coverage and handoff
- Changes:
  - Added a regression test proving approved-members-only layouts omit the manifest, service-worker registration, and Offline navigation entry.
  - Added the Offline Reading Runbook and linked it from the documentation index, covering snapshot bounds, refresh behavior, privacy, and browser-cache recovery.
- Verification:
  - Focused suite: `php tests/run.php LocalAppSmokeTest WebServerRoutingTest PublicOfflineSnapshotBuilderTest` — 107 run, 102 passed; the five failures are the pre-existing long-standing activity/signature failures.
  - Full suite: `php tests/run.php` — 588 run, 582 passed. All six failures are long-standing and unrelated: the same five activity/signature failures plus `LazyComposeSigningTest::testFirstComposeIntentLoadsSigningAssetsAndInitializesComposer`.
  - PHP lint, both service-worker/registration `node --check` checks, and `git diff --check` passed.
- Notes:
  - The normal UI and local reader list/detail were manually rendered in Chromium earlier in this cycle. A fresh browser offline-restart check still needs release QA on a normal browser because this environment's Snap Chromium does not persist an explicit temporary profile.

## Follow-up - Prototype cache isolation
- Changes:
  - Restricted service-worker registration and its manifest to the unlinked `/offline/` prototype, and moved its scope from `/` to `/offline/`.
  - Added a public-page cleanup script that unregisters the prior root-scoped prototype worker, and made online `/offline/` navigation refresh its shell before falling back to cache.
- Verification:
  - Covered public, private, and offline-reader layout contracts in `LocalAppSmokeTest`; routing coverage verifies the scoped worker's static path.
