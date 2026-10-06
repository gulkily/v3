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
