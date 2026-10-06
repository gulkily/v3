> **Feature plan:** [Step 1](./offline_reader_asset_refresh_step1_solution_assessment.md) · [Step 2](./offline_reader_asset_refresh_step2_feature_description.md) · [Step 3](./offline_reader_asset_refresh_step3_development_plan.md) · [Step 4](./offline_reader_asset_refresh_step4_implementation_summary.md)

# Offline Reader Asset Refresh: Implementation Summary

## Stage 1 - Snapshot manifest
- Changes:
  - `PublicOfflineSnapshotBuilder::build()` writes `offline/manifest.json` after the snapshot rename.
  - New private `writeManifest()`: fields `snapshot_version`, `generated_at`, `size_bytes`, `sha256`; written to a temp file, then renamed.
  - New `tests/PublicOfflineSnapshotManifestTest.php`, registered in `tests/run.php`.
- Verification:
  - `php tests/run.php PublicOfflineSnapshotManifestTest`: 1 passed.
  - `php tests/run.php PublicOfflineSnapshotBuilderTest`: 1 passed.
  - `php tests/run.php OfflineSnapshotPublisherTest`: 2 passed.
  - Not applicable: UI, deployment, migration (no schema change).
- Notes:
  - **Plan gap:** `FrontController` serves only `/offline/snapshot.sqlite3`, through `resolveOfflineSnapshotPath()` with query and cookie checks. Nothing serves `/offline/manifest.json`, so Stage 3's worker fetch would fail. The approved Step 3 plan has no serving stage. Needs a plan amendment before Stage 3.

## Stage 2 - Optional snapshot
- Changes:
  - `public/service_worker.js`: `SNAPSHOT_URL` removed from the required refresh list; new `refreshSnapshot()` fetches and caches it after the required refresh and logs (does not throw) on failure.
  - `tests/OfflineNavigationWorkerTest.php`: new `testMissingSnapshotDoesNotBlockReaderShellAndAssetCaching`.
- Verification:
  - `php tests/run.php OfflineNavigationWorkerTest`: 4 passed.
  - New test fails against the pre-stage worker (refresh threw on the 404 snapshot) and passes against this change.
  - Not applicable: UI, deployment, migration.
- Notes:
  - Deviation from the Stage 2 plan wording: per-URL settlement was not implemented. `refreshResources()` still writes all required entries only after every required fetch succeeds, which Stage 6 requires. Only the snapshot is optional.

## Stage 3 - Embed snapshot revision in reader shell
- Changes:
  - New `src/ForumRewrite/Offline/OfflineSnapshotLocator.php`: `servedSnapshotPath()` (static root, then active release, same precedence as the front controller) and `manifestRevision()` (SHA-256 from the manifest beside the served snapshot, or `''`).
  - `FrontController::resolveOfflineSnapshotPath()` now uses the locator; its private `isValidOfflineSnapshot()` is removed.
  - `OfflineReaderController` takes the static HTML root and passes `snapshotRevision`; `Application` supplies it.
  - `templates/pages/offline_reader.php` adds `data-snapshot-revision`.
  - Tests: new `tests/OfflineSnapshotLocatorTest.php` (5 cases: none, static root, release only, static wins, malformed manifest); new `testOfflineReaderEmbedsManifestRevisionFromServedSnapshot` and an empty-attribute assertion in `LocalAppSmokeTest`; registered in `tests/run.php`.
- Verification:
  - `php tests/run.php OfflineSnapshotLocatorTest`: 5 passed.
  - `php tests/run.php OfflineSnapshotPublisherTest`: 2 passed. `PublicOfflineSnapshotManifestTest`: 1 passed.
  - `php tests/run.php LocalAppSmokeTest`: 116 passed, 4 failed. The same 4 fail on a clean worktree at the pre-stage commit (`testAnonymousPublicBoardDoesNotStartViewerSession`, `testPostAndActivityLinkAdjacentSignatureFiles`, `testApplicationRendersTextApisAndRss`, `testSqliteViewerRouteUsesToolsShellAndPublishedSource`), so they predate this work. The new reader tests pass.
  - Not applicable: UI in a browser (covered in Stage 8), deployment, migration.
- Notes:
  - `FrontController` still has its own copy of the active-release lookup (used by its other route); the locator has a copy too. Consolidating them is a follow-up, not part of this stage.
  - The embedded value reflects the manifest, which is written after the snapshot rename. A render in the brief window between the two self-corrects on the next load.

## Stage 4 - Worker revision
- Changes:
  - `public/service_worker.js`: `revisionFromBodies()` (SHA-256 hex over the three shell bodies); `currentRevision()` (fetches the shells and hashes them); `storeRevision()` and `storedRevision()` (a non-served cache key, `REVISION_KEY`).
  - `refreshOfflineReader()` stores the revision computed from the shell bodies it already fetched, after a successful refresh. No extra requests.
  - The worker does not fetch `manifest.json`.
  - `tests/OfflineNavigationWorkerTest.php`: new `testReaderRevisionFollowsEmbeddedSnapshotRevisionAndIsStoredByRefresh`. The existing worker harnesses now provide `crypto` and `TextEncoder`, as the real worker context does.
- Verification:
  - `php tests/run.php OfflineNavigationWorkerTest`: 5 passed.
  - The new test checks: a 64-hex digest; the same shells give the same revision; a changed embedded `data-snapshot-revision` changes it; a refresh stores the revision computed from the shells it fetched.
  - Not applicable: UI, deployment, migration.
- Notes:
  - `currentRevision()` and `storedRevision()` are not used by production code until Stage 5 gates the load-time refresh on them.
  - `CACHE_NAME` is still v13; it moves to v14 in Stage 5 with the behavior change.

## Stage 5 - Gate load-time refresh on revision; failed refresh keeps previous copy
- Changes:
  - `public/service_worker.js`: `refresh-offline-reader` computes `currentRevision()`; if it equals `storedRevision()`, replies `unchanged` and returns without fetching assets or the snapshot. Otherwise refreshes as before.
  - `CACHE_NAME` bumped to `zenmemes-offline-reader-v14`.
  - `public/assets/offline_health.js`: `requestReaderRefresh()` accepts `unchanged` as success (see Notes).
  - `tests/OfflineNavigationWorkerTest.php`: new `testUnchangedRevisionSkipsRefreshAndFailedRefreshKeepsPreviousRevision` (first refresh ready; unchanged load makes zero asset and snapshot fetches; changed shell with a failing asset replies `error` and keeps the old revision and cached asset; recovery replies `ready`). Older cache mocks gain `match`.
  - `tests/LocalAppSmokeTest.php`: v13 assertion updated to v14 (the bump requires it; this was planned for Stage 7).
- Verification:
  - `php tests/run.php OfflineNavigationWorkerTest`: 6 passed.
  - `php tests/run.php LocalAppSmokeTest`: 116 passed, 4 failed; the same four pre-existing failures as the Stage 3 baseline.
  - `OfflineSnapshotPresentationTest` (7), `OfflineReadingDiagnosticCommandTest` (3), `OfflineSnapshotPublishCommandTest` (3), `OfflineSnapshotThreadPresentationTest` (2), `OfflineOutboxStateTest` (3): all pass.
  - Not applicable: UI in a browser (Stage 8), deployment, migration.
- Notes:
  - Deviation: `offline_health.js` change was not in the Stage 5 plan. Without it, the manual button would report failure whenever the revision is unchanged, until Stage 6 adds `force`.
  - On a changed revision, the shell pages are fetched twice in one refresh (once to compare, once inside `refreshOfflineReader()`). This is three small requests, only when content has changed. It can be removed by passing the bodies through, if it matters.
  - Stage 7's test updates for v14 are partly done here.

## Stage 6 - Manual refresh always refreshes
- Changes:
  - `public/assets/offline_health.js`: `requestReaderRefresh()` sends `force: true`.
  - `public/service_worker.js`: the revision check is skipped when `force` is set.
  - `tests/OfflineNavigationWorkerTest.php`: new `testForcedRefreshRefetchesEvenWhenRevisionMatches` (unchanged load makes no asset requests; forced load refetches assets and replies `ready`).
- Verification:
  - `php tests/run.php OfflineNavigationWorkerTest`: 7 passed.
  - `php tests/run.php LocalAppSmokeTest`: 116 passed, 4 failed; the same four pre-existing failures.
  - Not applicable: UI in a browser (Stage 8), deployment, migration.
- Notes:
  - With `force` in place, the Stage 5 `unchanged` acceptance in `offline_health.js` is no longer exercised by the button, but it stays as the contract for any other caller.
