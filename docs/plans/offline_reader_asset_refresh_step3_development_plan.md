> **Feature plan:** [Step 1](./offline_reader_asset_refresh_step1_solution_assessment.md) · [Step 2](./offline_reader_asset_refresh_step2_feature_description.md) · [Step 3](./offline_reader_asset_refresh_step3_development_plan.md) · [Step 4](./offline_reader_asset_refresh_step4_implementation_summary.md)

# Offline Reader Asset Refresh: Development Plan

## Amendment (requires re-approval)
- The reader shell embeds the published snapshot's revision, read from `manifest.json` at render time. The worker no longer fetches the manifest, so the `/offline/manifest.json` route and its serving stage are dropped.
- The manifest writer (Stage 1) is kept: the render reads it from disk.
- The revision is now a hash of the three shell bodies only; the snapshot's identity arrives inside them.
- Stage 6 (failed refresh keeps the previous copy) is folded into the gating stage, because `refreshResources()` already writes only after every required fetch succeeds.

## Completion Contract
- Normal entry: a reader opens any page with the saved reader enabled.
- End-to-end outcome: five quiet loads make no `/assets/*` or snapshot requests; a snapshot publish refreshes once on the next load, then stops.
- Required recovery: "Refresh saved reader" forces a refresh regardless of revision.
- Deployment/external verification: publish a snapshot on a local build and confirm the manifest matches the file and the reader shell embeds the same value; no remote deployment in scope.
- Release condition: Stage 8 manual verification passes and the offline worker, navigation, and smoke tests pass.
- Note: the three shell pages are still fetched on each load to compute the revision. They are small and are not counted by the Step 2 criteria.

## Key Risks
- **High risk: stale offline content.** A change the revision does not detect leaves readers on old content.
  - Impact: wrong or missing content offline.
  - Early validation: Stage 3 tests that the shell embeds the revision; Stage 5 tests that a changed shell or snapshot triggers a refresh.
  - Mitigation: the shell bodies carry the snapshot revision; the manual button forces a refresh.
- **Manifest and snapshot out of sync.** Impact: a refresh that never happens or repeats.
  - Early validation: Stage 1 test that the manifest hash matches the snapshot after each build.
  - Mitigation: write the manifest only after the snapshot is replaced. A render in the brief window between the two self-corrects on the next load, because the attribute then changes.
- **Missing snapshot blocks caching.** Impact: no saved reader where no snapshot exists.
  - Early validation: Stage 2 test with a 404 snapshot.
  - Mitigation: snapshot is optional; the revision is stored only after required shell and asset caching succeeds.
- **Manifest and snapshot read from different roots.** The front controller also checks an active static release root. Impact: the embedded revision describes a different file from the one served.
  - Early validation: Stage 3 test covering both roots.
  - Mitigation: resolve the manifest with the same rules as the snapshot.

## Stage 1 (done)
- Goal: write `offline/manifest.json` beside the snapshot, containing its generated time, size, and SHA-256.
- Dependencies: none.
- Expected changes:
  - `PublicOfflineSnapshotBuilder::build()` writes the manifest after the atomic snapshot replace.
  - New private method `writeManifest(string $snapshotPath, string $generatedAt, int $size): void`.
  - No schema change.
- Verification approach: test builds a snapshot twice, checks the manifest hash equals the file hash each time and changes with the data.
- Risks or open questions:
  - Impact: manifest older than snapshot.
  - Mitigation: write after rename; temp file then rename.
- Canonical components/API contracts touched:
  - Manifest file contract (new): `{snapshot_version, generated_at, size_bytes, sha256}`.

## Stage 2 (done)
- Goal: snapshot is optional in the worker's refresh.
- Dependencies: none.
- Expected changes:
  - `refreshSnapshot()` fetches and caches the snapshot after the required refresh; failure is logged, not thrown.
- Verification approach: worker test with a 404 snapshot; shell and assets still cached.
- Risks or open questions:
  - Impact: partial cache with no revision.
  - Mitigation: revision is stored only after required entries succeed (Stage 5).
- Canonical components/API contracts touched:
  - `service_worker.js` refresh logic.

## Stage 3
- Goal: the reader shell embeds the published snapshot's revision.
- Dependencies: Stage 1.
- Expected changes:
  - `OfflineReaderController` reads `manifest.json` from the snapshot's resolved root and passes `snapshotRevision` (the manifest's `sha256`, or empty if absent).
  - `templates/pages/offline_reader.php` adds `data-snapshot-revision`.
  - New private method `snapshotRevision(): string`.
  - `data-reader-revision` is unchanged.
- Verification approach: render test: attribute equals manifest `sha256`; empty with no manifest; changes after a second publish; static-release root case covered.
- Risks or open questions:
  - Impact: per-render disk read adds a small cost.
  - Mitigation: a single small file read; no SQLite access.
- Canonical components/API contracts touched:
  - Reader shell data attribute `data-snapshot-revision` (new).

## Stage 4
- Goal: the worker computes and stores a saved-reader revision.
- Dependencies: Stage 3.
- Expected changes:
  - `currentRevision(): Promise<string>`: SHA-256 over the three fetched shell bodies.
  - `storedRevision()` and `storeRevision(value)` using a non-URL key in the same cache.
  - The worker does not fetch the manifest.
- Verification approach: worker test: same inputs give the same revision; changed shell body (including a changed `data-snapshot-revision`) gives a different one.
- Risks or open questions:
  - Impact: unstable markup causes a refresh on every load.
  - Mitigation: hash only fetched bodies; the render adds no per-request values beyond the snapshot revision.
- Canonical components/API contracts touched:
  - Service worker internal revision contract.

## Stage 5
- Goal: the load-time refresh does nothing when the revision is unchanged, and a failed refresh keeps the previous saved copy.
- Dependencies: Stage 4.
- Expected changes:
  - `refresh-offline-reader` handler: compute revision; if equal to stored, reply `unchanged` and fetch no assets or snapshot.
  - Otherwise refresh once and store the revision.
  - Writes happen only after every required fetch succeeds.
  - `CACHE_NAME` bumped to `zenmemes-offline-reader-v14`.
  - `pwa_registration.js` trigger unchanged.
- Verification approach: worker test counts fetches: unchanged makes none to `/assets/*` or snapshot; changed refreshes once, then none. Failing asset fetch leaves old entries and old revision in place.
- Risks or open questions:
  - Impact: a changed asset not picked up.
  - Mitigation: manual force (Stage 6).
- Canonical components/API contracts touched:
  - Message contract: `refresh-offline-reader` replies `ready`, `unchanged`, or `error`.

## Stage 6
- Goal: "Refresh saved reader" always refreshes.
- Dependencies: Stage 5.
- Expected changes:
  - `offline_health.js` `requestReaderRefresh()` sends `force: true`.
  - Worker skips the revision check when `force` is set.
- Verification approach: worker test: forced refresh fetches despite a matching revision and replies `ready`.
- Risks or open questions:
  - Impact: none beyond extra requests when pressed.
- Canonical components/API contracts touched:
  - `offline_health.js` manual refresh (reused).

## Stage 7
- Goal: update existing tests to the new behavior.
- Dependencies: Stages 1–6.
- Expected changes:
  - `tests/OfflineNavigationWorkerTest.php`: v14 name, `unchanged` status.
  - `tests/LocalAppSmokeTest.php`: worker source assertions.
- Verification approach: run the offline worker, navigation, and smoke tests.
- Risks or open questions:
  - Impact: none expected.
- Canonical components/API contracts touched:
  - Test contracts for the worker message.

## Stage 8
- Goal: confirm the Completion Contract on a local build.
- Dependencies: Stages 1–7.
- Expected changes: none (verification only).
- Verification approach:
  - Publish a snapshot, confirm the manifest hash matches the file and the reader shell embeds it.
  - Open the reader and watch the dev server log for five quiet loads.
  - Publish again and confirm exactly one refresh.
  - Press "Refresh saved reader" and confirm it refreshes.
  - Recorded in the Step 4 summary.
- Risks or open questions:
  - Impact: snapshot publish requires approved-members-only off.
- Canonical components/API contracts touched:
  - `scripts/publish_offline_snapshot.php` (used, unchanged).

Waiting for "Approved Step 3" on this amended plan before implementing Stage 3.
