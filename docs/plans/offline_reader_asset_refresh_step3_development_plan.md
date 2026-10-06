> **Feature plan:** [Step 1](./offline_reader_asset_refresh_step1_solution_assessment.md) · [Step 2](./offline_reader_asset_refresh_step2_feature_description.md) · [Step 3](./offline_reader_asset_refresh_step3_development_plan.md) · [Step 4](./offline_reader_asset_refresh_step4_implementation_summary.md)

# Offline Reader Asset Refresh: Development Plan

## Completion Contract
- Normal entry: a reader opens any page with the saved reader enabled.
- End-to-end outcome: five quiet loads make no `/assets/*` or snapshot requests; a snapshot publish refreshes once on the next load, then stops.
- Required recovery: "Refresh saved reader" forces a refresh regardless of revision.
- Deployment/external verification: publish a snapshot on a local build and confirm the manifest matches the file; no remote deployment in scope.
- Release condition: Stage 8 manual verification passes and the offline worker, navigation, and smoke tests pass.
- Note: the three shell pages are still fetched on each load to compute the revision. They are small and are not counted by the Step 2 criteria.

## Key Risks
- **High risk: stale offline content.** A change the revision does not detect leaves readers on old content.
  - Impact: wrong or missing content offline.
  - Early validation: Stage 4 tests that a shell, asset, or snapshot change alters the revision.
  - Mitigation: revision covers shell bodies and the snapshot file hash; the manual button forces a refresh.
- **Manifest and snapshot out of sync.** Impact: a refresh that never happens or repeats.
  - Early validation: Stage 1 test that the manifest hash matches the snapshot after each build.
  - Mitigation: write the manifest only after the snapshot is replaced.
- **Missing snapshot blocks caching.** Impact: no saved reader where no snapshot exists.
  - Early validation: Stage 2 test with a 404 snapshot.
  - Mitigation: snapshot is optional; the revision is stored only after required shell and asset caching succeeds.

## Stage 1
- Goal: write `offline/manifest.json` beside the snapshot, containing its generated time, size, and SHA-256.
- Dependencies: none.
- Expected changes:
  - `PublicOfflineSnapshotBuilder::build()` writes the manifest after the atomic snapshot replace.
  - New private method `writeManifest(string $snapshotPath, array $metadata): void`.
  - No schema change.
- Verification approach: new PHP test builds a snapshot twice, checks the manifest hash equals the file hash each time, and that it changes when the data changes.
- Risks or open questions:
  - Impact: manifest older than snapshot.
  - Early warning / validation: the test above.
  - Mitigation: write after rename; write via temp file then rename.
- Canonical components/API contracts touched:
  - Manifest file contract (new): `{generated_at, size_bytes, sha256}`.

## Stage 2
- Goal: snapshot is optional in the worker's refresh.
- Dependencies: none.
- Expected changes:
  - `refreshResources()` uses per-URL settlement, so one failed asset does not discard the rest.
  - Snapshot 404 is logged and skipped.
- Verification approach: worker test with a 404 snapshot; shell and assets are still cached.
- Risks or open questions:
  - Impact: partial cache with no revision.
  - Early warning / validation: test asserts no revision is stored on partial failure.
  - Mitigation: revision is stored only on full success of required URLs.
- Canonical components/API contracts touched:
  - `service_worker.js` refresh logic.

## Stage 3
- Goal: the worker computes and stores a saved-reader revision.
- Dependencies: Stage 1 (manifest), Stage 2.
- Expected changes:
  - `currentRevision(): Promise<string>`: SHA-256 over the three shell bodies and the manifest's SHA-256.
  - `storedRevision()` and `storeRevision(value)` using a non-URL key in the same cache.
  - `data-reader-revision` is left unchanged; the worker does not read it.
- Verification approach: worker test: same inputs give the same revision; changed shell body or manifest hash gives a different one.
- Risks or open questions:
  - Impact: unstable hash (for example, a timestamp in a shell body) causes a refresh on every load.
  - Early warning / validation: test that repeated computation is identical.
  - Mitigation: hash only fetched bodies, not generated HTML with per-request values.
- Canonical components/API contracts touched:
  - Service worker internal revision contract.

## Stage 4
- Goal: the load-time refresh does nothing when the revision is unchanged.
- Dependencies: Stage 3.
- Expected changes:
  - `refresh-offline-reader` handler: compute revision; if equal to stored, reply `unchanged` and fetch no assets or snapshot.
  - Otherwise refresh once and store the revision.
  - `CACHE_NAME` bumped to `zenmemes-offline-reader-v14`.
  - `pwa_registration.js` trigger unchanged.
- Verification approach: worker test counts fetches: unchanged makes none to `/assets/*` or snapshot; changed refreshes once, then none.
- Risks or open questions:
  - Impact: a changed asset not picked up.
  - Early warning / validation: the Stage 3 and Stage 4 tests.
  - Mitigation: manual force (Stage 5).
- Canonical components/API contracts touched:
  - Message contract: `refresh-offline-reader` replies `ready`, `unchanged`, or `error`.

## Stage 5
- Goal: "Refresh saved reader" always refreshes.
- Dependencies: Stage 4.
- Expected changes:
  - `offline_health.js` `requestReaderRefresh()` sends `force: true`.
  - Worker skips the revision check when `force` is set.
- Verification approach: worker test: forced refresh fetches despite a matching revision and replies `ready`.
- Risks or open questions:
  - Impact: none expected beyond extra requests when pressed.
- Canonical components/API contracts touched:
  - `offline_health.js` manual refresh (reused).

## Stage 6
- Goal: a failed refresh keeps the previous saved copy.
- Dependencies: Stage 4.
- Expected changes:
  - Refresh writes to the cache only after all required fetches succeed.
- Verification approach: worker test: a failing asset fetch leaves the old entries and the old revision in place.
- Risks or open questions:
  - Impact: mixed old and new assets.
  - Early warning / validation: the test above.
  - Mitigation: stage new responses, then write them all together.
- Canonical components/API contracts touched:
  - Service worker cache writes.

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
  - Publish a snapshot, open the reader, and watch the dev server log for five quiet loads.
  - Publish again and confirm exactly one refresh.
  - Press "Refresh saved reader" and confirm it refreshes.
  - Recorded in the Step 4 summary.
- Risks or open questions:
  - Impact: snapshot publish requires approved-members-only off.
- Canonical components/API contracts touched:
  - `scripts/publish_offline_snapshot.php` (used, unchanged).

## Notes
- Deviation from Step 2: `data-reader-revision` is not changed; the worker derives its revision itself. This removes a change, not adds one.

Waiting for "Approved Step 3" before the Step 4 branch is created.
