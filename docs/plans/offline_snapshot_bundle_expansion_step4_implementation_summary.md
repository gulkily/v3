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
