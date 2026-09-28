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
