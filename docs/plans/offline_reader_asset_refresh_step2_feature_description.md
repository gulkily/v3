> **Feature plan:** [Step 1](./offline_reader_asset_refresh_step1_solution_assessment.md) · [Step 2](./offline_reader_asset_refresh_step2_feature_description.md) · [Step 3](./offline_reader_asset_refresh_step3_development_plan.md) · [Step 4](./offline_reader_asset_refresh_step4_implementation_summary.md)

# Offline Reader Asset Refresh: Feature Description

## Problem
Every normal page load re-downloads the saved-reader assets and the offline snapshot through the service worker, even when nothing has changed.

## User Stories
- As a reader, I want page navigation to stop re-downloading unchanged scripts and styles so that pages open faster and use less data.
- As a reader, I want the saved reader to pick up a new snapshot or deploy without manual steps so that I see current content offline.
- As an operator, I want a published snapshot or deploy to refresh readers' saved copies automatically so that I do not have to ask users to press a button.

## Core Requirements
- A normal page load makes no asset or snapshot requests when the saved-reader revision is unchanged.
- The saved-reader revision changes when either the static reader shell (including its asset references) or the published snapshot changes.
- A changed revision triggers one refresh of the changed content on the next page load.
- A missing or failed snapshot must not block caching of the reader shell and assets.
- The existing "Refresh saved reader" button remains the manual recovery path.

## Delivery Scope
- Work type: application change
- Not in scope: new cache strategy for non-reader pages, changes to the outbox, changes to the database schema.

## Completion Boundary
- Normal entry: a reader opens any page with the saved reader enabled.
- End-to-end outcome: unchanged loads make no asset or snapshot requests; a changed snapshot or deploy refreshes the saved copy once.
- Needed recovery: the "Refresh saved reader" button forces a refresh.
- Release condition: the scenarios in Success Criteria pass on a local build, and existing offline tests are updated.

## Risks
- **Stale offline content.** If a change to the shell, an asset, or the snapshot does not change the revision, readers keep old content. Impact: wrong or missing content offline. Earliest validation: a Step 3 stage that changes one asset and one snapshot and checks the revision each time. Mitigation: the revision covers all three shell page bodies and the snapshot's `generated_at`; the manual button stays available.
- **Revision out of sync with the snapshot.** The manifest could be written without the matching snapshot, or the reverse. Impact: a refresh that never happens, or a needless one. Earliest validation: a Step 3 stage that publishes a snapshot and reads the manifest. Mitigation: write the manifest in the same step as the snapshot, with the snapshot replaced first.
- **Missing snapshot blocks caching.** The snapshot 404 in the log makes the current refresh store nothing. Impact: no saved reader at all in environments without a snapshot. Earliest validation: a test with a 404 snapshot. Mitigation: treat a missing snapshot as a non-fatal case, and cache the shell and assets.

## Shared Component Inventory
- `public/assets/pwa_registration.js`: runs the load-time refresh. **Extend**: keep the trigger, change its condition to the revision check.
- `public/service_worker.js`: holds the refresh and cache logic. **Extend**: add the revision check and skip unchanged content.
- `public/assets/offline_health.js`: the manual "Refresh saved reader" button. **Reuse** as the recovery path; no change planned.
- `templates/pages/offline_reader.php`: exposes `data-reader-revision`, currently only the fingerprinted URL of `offline_reader.js`. **Extend**: the revision must reflect the shell and snapshot, so the attribute's source changes.
- `src/ForumRewrite/Offline/PublicOfflineSnapshotBuilder.php`: already writes `generated_at` into snapshot metadata. **Reuse** that value as the snapshot revision; the builder does not need a new schema.

## User Flow
1. Reader opens a page with the saved reader enabled.
2. The page registers the worker and checks the revision.
3. If the revision is unchanged, nothing else is fetched.
4. If it changed, the worker refreshes the shell, assets, and snapshot once and stores the new revision.
5. If a refresh fails, the reader keeps the previous saved copy and can press "Refresh saved reader".

## Success Criteria
- Five consecutive page loads with no publish or deploy in between make zero requests to `/assets/*` and `/offline/snapshot.sqlite3` with the bootstrap query.
- After a snapshot publish, the next page load refreshes once; the load after that makes no requests.
- After a change to one static asset, the next page load refreshes and picks it up.
- With the snapshot missing (404), the shell and assets are still cached.
- The "Refresh saved reader" button still refreshes on demand.

## Design note
The manifest is a static file written beside `snapshot.sqlite3`, not a PHP route. Step 1 said `/offline/manifest.json`; this is the same URL with no new route.

Waiting for "Approved Step 2" before drafting Step 3.
