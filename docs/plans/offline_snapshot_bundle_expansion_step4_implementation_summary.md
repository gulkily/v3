# Offline Snapshot Bundle Expansion — Step 4 Implementation Summary

> **Feature plan:** [Step 1](./offline_snapshot_bundle_expansion_step1_solution_assessment.md) · [Step 2](./offline_snapshot_bundle_expansion_step2_feature_description.md) · [Step 3](./offline_snapshot_bundle_expansion_step3_development_plan.md) · [Step 4](./offline_snapshot_bundle_expansion_step4_implementation_summary.md)

## Stage 1 - Define bounded import contracts

- Changes:
  - Raised the default public base to 200 recent non-pinned threads and 100 MiB.
  - Added snapshot version 3 metadata and a compact `public_keys` import.
  - Included every approved key plus only unapproved keys associated with
    selected visible content.
- Verification:
  - `php -l` passed for the builder and focused tests.
  - `php tests/run.php PublicOfflineSnapshotBuilderTest PublicOfflineSnapshotManifestTest OfflineSnapshotPublisherTest` passed: 4 run, 4 passed.
- Notes:
  - The full-base publisher remains backward-compatible at its existing entry
    point; compact update publication follows in Stage 2.

## Stage 2 - Publish the base and compact update safely

- Changes:
  - Added a bounded 20-thread, 8 MiB compact update builder and independent
    atomic `offline/update.sqlite3` publication with its own manifest.
  - Kept the existing full-base snapshot path and manifest unchanged.
- Verification:
  - `php -l` passed for the builder and publisher.
  - `php tests/run.php OfflineSnapshotPublisherTest PublicOfflineSnapshotManifestTest PublicOfflineSnapshotBuilderTest` passed: 5 run, 5 passed.
- Notes:
  - Queue priority, public serving, and client import remain staged work.

## Stage 3 - Prioritize fresh updates and coalesce base work

- Changes:
  - Made the existing deduplicated publication task publish the compact update
    before rebuilding the larger base, preserving write-time asynchrony.
- Verification:
  - `php tests/run.php TaskQueueCommandTest TaskQueueWorkerTest` passed the
    offline publication case and 20 of 22 focused checks; two existing default
    queue-state checks fail outside this feature's isolated fixture path.
- Notes:
  - The single existing queue task still coalesces bursts; the compact artifact
    is now available before the full-base publication completes.

## Stage 4 - Apply updates to one saved client database

- Changes:
  - Cached and anonymously served the compact update alongside the base.
  - Applied update rows with idempotent upserts in the existing reader, then
    atomically replaced the cached SQLite bytes while preserving the base on
    update failure.
- Verification:
  - `node --check public/service_worker.js` and `node --check public/assets/offline_reader.js` passed.
  - `php tests/run.php OfflineNavigationWorkerTest OfflineSnapshotPresentationTest OfflineSnapshotThreadPresentationTest` passed: 18 run, 18 passed.
- Notes:
  - Legacy snapshots without the update table remain readable because a failed
    optional update is ignored.

## Stage 5 - Surface state and verify the vertical slice

- Changes:
  - Added saved public-key count to Offline Reading health diagnostics.
  - Documented the enlarged import's approved-key guarantee, optional
    content-associated unapproved keys, and cache-retention boundary.
- Verification:
  - Focused JavaScript/navigation checks completed in Stage 4; documentation
    changes were checked with `git diff --check`.
- Notes:
  - Production deployment verification remains an operator release step:
    publish, reconnect online, then confirm supported offline navigation.
