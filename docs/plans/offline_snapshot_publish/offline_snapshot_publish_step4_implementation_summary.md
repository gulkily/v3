# Offline Snapshot Publish Step 4 Implementation Summary

## Stage 1 - Add atomic snapshot publisher
- Changes:
  - Added `OfflineSnapshotPublisher`, which reuses the bounded public snapshot
    builder and publishes only `offline/snapshot.sqlite3` outside static
    releases.
  - Added focused coverage for successful publication and preserving the prior
    snapshot when the source database is unavailable.
- Verification:
  - `php -l src/ForumRewrite/Offline/OfflineSnapshotPublisher.php` and
    `php -l tests/OfflineSnapshotPublisherTest.php` passed.
  - `php tests/run.php OfflineSnapshotPublisherTest` passed: 2 run, 2 passed.
- Notes:
  - The underlying builder writes a temporary sibling then renames it into
    place, so a successful replacement is atomic on the static-root filesystem.

## Stage 2 - Prefer and diagnose the independent snapshot
- Changes:
  - Made the anonymous snapshot endpoint prefer a valid independently published
    snapshot and fall back to the active static-release snapshot.
  - Made diagnosis report whether the selected local snapshot is the independent
    publication or the release fallback.
- Verification:
  - `php -l` passed for the front controller, diagnostic script, and focused
    tests.
  - `php tests/run.php LocalAppSmokeTest::testFrontControllerServesPublicOfflineSnapshotFromActiveRelease LocalAppSmokeTest::testFrontControllerPrefersIndependentlyPublishedOfflineSnapshot LocalAppSmokeTest::testFrontControllerFallsBackWhenIndependentOfflineSnapshotIsInvalid OfflineReadingDiagnosticCommandTest` passed: 5 run, 5 passed.
- Notes:
  - An invalid independent file cannot mask a valid active-release snapshot;
    approved-members-only still bypasses both sources.
