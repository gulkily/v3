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

## Stage 3 - Add the guarded operator command
- Changes:
  - Added `./v3 offline publish` with repository, read-model, and static-root
    overrides; it publishes only the independent snapshot.
  - Added approved-members-only refusal, publication metadata, read-model
    freshness guidance, and a diagnosis next step.
  - Updated diagnosis to recommend the fast publish command.
- Verification:
  - `php -l scripts/publish_offline_snapshot.php`, `bash -n v3`, and
    `php -l tests/OfflineSnapshotPublishCommandTest.php` passed.
  - `php tests/run.php OfflineSnapshotPublishCommandTest OfflineReadingDiagnosticCommandTest` passed: 4 run, 4 passed.
- Notes:
  - The command deliberately does not deploy `sql-wasm.wasm`, refresh browser
    caches, or make the read model current; those remain separate operations.

## Stage 4 - Document and protect the operator workflow
- Changes:
  - Documented the fast publish-and-diagnose workflow, its data freshness
    prerequisite, browser refresh requirement, asset-deployment boundary, and
    static-release fallback.
  - Added the completed feature to the plans index.
- Verification:
  - `php tests/run.php OfflineSnapshotPublisherTest OfflineSnapshotPublishCommandTest OfflineReadingDiagnosticCommandTest LocalAppSmokeTest::testFrontControllerServesPublicOfflineSnapshotFromActiveRelease LocalAppSmokeTest::testFrontControllerPrefersIndependentlyPublishedOfflineSnapshot LocalAppSmokeTest::testFrontControllerFallsBackWhenIndependentOfflineSnapshotIsInvalid LocalAppSmokeTest::testFrontControllerDoesNotServeOfflineSnapshotWhenMembersOnlyIsEnabled` passed: 10 run, 10 passed.
- Notes:
  - Operators can use `./v3 offline diagnose --url=https://your-public-domain`
    after publishing to distinguish an application-asset deployment issue from
    a snapshot publication issue.

## Follow-up - Use the canonical SQLite runtime URL
- Changes:
  - Rendered the fingerprinted WASM URL into offline-reader, health, and
    SQLite-viewer pages; their loaders now request that URL explicitly.
  - Updated service-worker refresh to discover the runtime URL from the reader
    shell, removed the bare runtime request, and advanced the cache generation.
  - Made diagnosis check the fingerprinted public runtime endpoint.
- Verification:
  - Focused offline, reader, health, SQLite-viewer, and diagnosis tests passed.
- Notes:
  - This fixes hosts that serve the normal fingerprinted asset but return 404
    for the bare `/assets/sql-wasm.wasm` filename.
