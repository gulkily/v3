# Multi-Site Refactor P2 Browser and Offline Identity — Step 4: Implementation Summary

> **Feature plan:** [Step 1](./multi_site_refactor_p2_browser_offline_identity_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p2_browser_offline_identity_step2_feature_description.md) · [Step 3](./multi_site_refactor_p2_browser_offline_identity_step3_development_plan.md) · [Step 4](./multi_site_refactor_p2_browser_offline_identity_step4_implementation_summary.md)

## Stage 1 - Browser runtime descriptor

- Changes:
  - Added `BrowserRuntimeProfile`, deriving the profile-owned preference, cache, diagnostic, and manifest identifiers from the validated browser namespace.
  - Added registry coverage for all three descriptors and invalid runtime identity rejection.
- Verification:
  - `php tests/run.php SiteProfileRegistryTest` — 8 passed.
  - `git diff --check` — passed.
- Notes:
  - Authentication, session, and browser-held identity storage are intentionally absent from the descriptor.

## Stage 2 - Profile-owned preferences

- Changes:
  - Passed the browser-runtime descriptor into the shared layout and exposed it to the existing theme and density controls.
  - Replaced hard-coded preference access with the descriptor's theme and density keys; retained a Zenmemes-only absent-key migration boundary.
  - Kept browser identity, session, and authentication storage entirely outside this work.
- Verification:
  - `php tests/run.php ProfileThemePresentationTest LocalAppSmokeTest::testThreadDensityToggleIsHiddenByDefaultAndShownWhenFlagEnabled` — 3 passed.
  - `git diff --check` — passed.
- Notes:
  - The new `forum-{namespace}-…` keys make the Zenmemes fallback an actual one-time migration; Chouse and QDB never read or overwrite legacy Zenmemes preferences.

## Stage 3 - Profile-owned offline cache lifecycle

- Changes:
  - Made worker cache identity, cleanup, bootstrap validation, registration diagnostics, and offline-health cache discovery derive from the active runtime descriptor.
  - Raised the owned cache revision to `v14`; activation now removes stale caches only from the active profile's cache family.
  - Added a worker isolation test that proves a Chouse worker leaves Zenmemes and QDB cache names untouched.
- Verification:
  - `php tests/run.php OfflineNavigationWorkerTest LocalAppSmokeTest::testPublicLayoutRegistersTheNormalNavigationOfflineWorker LocalAppSmokeTest::testOfflineSnapshotAllowsWorkerBootstrapQueries` — 5 passed.
  - `git diff --check` — passed.
- Notes:
  - Runtime configuration delivery for non-Zen profiles is completed in the next stage; the checked-in source remains the safe Zenmemes fallback for direct development serving.

## Stage 4 - Dynamic PWA runtime delivery

- Changes:
  - Added a shared browser-runtime asset renderer for profile-derived manifests and worker configuration.
  - Routed manifest and worker requests through the front controller instead of allowing web servers to serve Zenmemes source files directly.
  - Allowed matching historical cache-family bootstrap requests while still rejecting a foreign profile's cache family.
- Verification:
  - `php tests/run.php WebServerRoutingTest LocalAppSmokeTest::testFrontControllerServesPublicOfflineSnapshotFromActiveRelease LocalAppSmokeTest::testFrontControllerDerivesManifestAndWorkerFromTheActiveProfile LocalAppSmokeTest::testFrontControllerDoesNotServeOfflineSnapshotWhenMembersOnlyIsEnabled` — 5 passed.
  - `git diff --check` — passed.
- Notes:
  - The root scope remains intentionally singular: changing the active profile updates that one worker rather than creating concurrent root-scope workers.

## Stage 5 - Static PWA runtime delivery

- Changes:
  - Replaced raw worker/manifest copying with the same profile-aware renderer used by dynamic delivery.
  - Kept favicon copying unchanged and verified static release refreshes now contain an injected profile runtime.
- Verification:
  - `php tests/run.php LocalAppSmokeTest::testStaticArtifactBuilderWritesApacheFriendlyArtifactLayout LocalAppSmokeTest::testSharedStaticRefreshCommandKeepsDetailArtifactsWithoutRebuildingTheReadModel` — 2 passed.
  - `git diff --check` — passed.
- Notes:
  - Static publication uses the server-selected profile at build time, matching the isolated static artifact root already derived from its browser namespace.

## Stage 6 - Browser/offline regression matrix

- Changes:
  - Extended dynamic manifest/worker coverage and physical static artifact coverage to Zenmemes, Chouse, and QDB.
  - Added a public-session regression that changes active profiles sequentially and confirms the authenticated identity remains available.
  - Marked the four verified P2 browser/offline checklist entries complete.
- Verification:
  - `php -l src/ForumRewrite/BrowserRuntimeProfile.php && php tests/run.php SiteProfileRegistryTest ProfileThemePresentationTest LocalAppSmokeTest::testThreadDensityToggleIsHiddenByDefaultAndShownWhenFlagEnabled LocalAppSmokeTest::testChangingTheActiveProfilePreservesThePublicSession LocalAppSmokeTest::testFrontControllerDerivesManifestAndWorkerFromTheActiveProfile LocalAppSmokeTest::testStaticArtifactBuilderDerivesRuntimeAssetsForEveryProfile OfflineNavigationWorkerTest` — syntax check passed; 18 tests passed.
  - `./v3 test` — 778 run, 770 passed, 8 failed; all 8 are recorded long-standing failures unrelated to this feature: two browser-signing Node harness tests, four pre-existing local-app smoke tests, the platform-docs page test, and the incremental approval/activity snapshot test.
  - `git diff --check` — passed.
- Notes:
  - Deployment remains an operator responsibility; this matrix validates both application-routed delivery and physical static files locally.
